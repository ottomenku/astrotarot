<?php

namespace App\Services;

use App\Models\Game;
use App\Models\ReadingLog;
use App\Models\ReadingSession;
use App\Models\User;
use App\Support\CelestialHelperBrief;
use App\Support\CelestialOracleCatalog;
use App\Support\TarotCardBrief;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TarotReadingService
{
    public const MODE_INITIAL = 'initial';

    public const MODE_CLARIFY = 'clarify';

    public const MODE_EXPAND = 'expand';

    public const MODE_REFINE = 'refine';

    public function __construct(
        private readonly OpenAIClient $openAI,
        private readonly OpenAIReadingAgent $agents,
        private readonly AppSettings $settings,
        private readonly FigureMeaningService $meanings,
        private readonly ReadingReplyParser $replies,
        private readonly ReadingSkyLookup $sky,
        private readonly PlaceGeocoder $places,
        private readonly CelestialHelperBrief $helperBrief,
        private readonly CardDrawProfileService $drawProfile,
        private readonly ClassifiedSkyBrief $classifiedSky,
        private readonly LlmTranscript $transcript,
    ) {}

    private bool $helperChartResolved = false;

    /** @var array{objects?: list<array<string, mixed>>, aspects?: list<array<string, mixed>>}|null */
    private ?array $helperChart = null;

    /**
     * @param  array{
     *   question: string,
     *   locale?: string,
     *   cards: list<array<string, mixed>>,
     *   astro?: array<string, mixed>,
     *   mode?: string,
     *   previous_reading?: string|null,
     *   follow_up?: string|null,
     *   system_prompt?: string|null,
     *   ip?: string|null,
     *   user_id?: int|null
     * }  $payload
     * @return array{text: string, source: array{provider: string, model: string, fallback: bool, label: string}, session_id?: string, light_shadow: list<array<string, mixed>>}
     */
    public function interpret(array $payload): array
    {
        $locale = ($payload['locale'] ?? 'hu') === 'en' ? 'en' : 'hu';
        $question = trim((string) ($payload['question'] ?? ''));
        $cards = $this->enrichCards(array_values($payload['cards'] ?? []), $locale);
        $astro = is_array($payload['astro'] ?? null) ? $payload['astro'] : [];
        $ip = isset($payload['ip']) ? (string) $payload['ip'] : null;
        $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;
        $mode = $this->normalizeMode((string) ($payload['mode'] ?? self::MODE_INITIAL));
        $previousReading = trim((string) ($payload['previous_reading'] ?? ''));
        $followUp = trim((string) ($payload['follow_up'] ?? ''));
        $requestedModel = $this->openAI->selectedModel();
        $overridePrompt = trim((string) ($payload['system_prompt'] ?? ''));

        if ($question === '' || $cards === [] || count($cards) > 49) {
            throw new RuntimeException('A reading requires a question and 1–49 cards.');
        }

        if (! in_array($mode, [self::MODE_INITIAL, self::MODE_REFINE], true) && $previousReading === '') {
            throw new RuntimeException('A follow-up requires the previous reading.');
        }

        $systemPrompt = $this->withLightShadowProtocol($this->settings->systemPrompt($locale), $locale, $cards);
        $spreadPrompt = $overridePrompt;
        $reader = $userId ? User::query()->find($userId) : null;
        $game = isset($payload['game_id']) ? Game::query()->find((int) $payload['game_id']) : null;
        $session = $this->session($payload, $reader, $locale, $question, $cards);
        $continuing = $mode !== self::MODE_INITIAL && $session->turns()->exists();
        $classified = $mode === self::MODE_INITIAL && filled($session->category);
        $classifiedSky = $classified
            ? $this->classifiedSky->block(
                $locale,
                $game,
                $reader,
                $payload,
                (string) ($session->category ?? ''),
                (string) ($session->dimension ?? ''),
            )
            : '';

        $plainThread = filled($session->category);
        $category = (string) ($session->category ?? '');
        $dimension = (string) ($session->dimension ?? '');
        $useAgent = ! $plainThread && $this->agents->enabled() && ! ($continuing && ! filled($session->llm_session_id));
        $userPrompt = $mode === self::MODE_REFINE
            ? $this->refineMessage($locale, $payload, $reader)
            : ($continuing
                ? $this->turnMessage($locale, $mode, $followUp, $previousReading)
                : ($useAgent
                    ? $this->variableMessage($locale, $question, $cards, $astro, $spreadPrompt, $reader, $game, $mode, $followUp, $previousReading, $category, $dimension)
                    : $this->openingMessage($locale, $question, $cards, $astro, $spreadPrompt, $reader, $game, $mode, $followUp, $previousReading, $classifiedSky, $classified, $category, $dimension)));
        $agentExtra = $useAgent
            ? $this->agentSessionInstructions($locale, $spreadPrompt, $cards)
            : '';

        $this->remember($session, 'user', $continuing ? $mode : 'opening', $userPrompt);

        $logQuestion = $mode === self::MODE_CLARIFY
            ? $question."\n\n[pontosítás] ".$followUp
            : ($mode === self::MODE_EXPAND ? $question."\n\n[bővebb kifejtés]" : $question);

        $log = ReadingLog::query()->create([
            'user_id' => $userId,
            'session_public_id' => $session->public_id,
            'locale' => $locale,
            'question' => mb_substr($logQuestion, 0, 280),
            'cards' => $cards,
            'astro' => array_merge($astro, [
                'mode' => $mode,
                'follow_up' => $followUp !== '' ? $followUp : null,
                'session_id' => $session->public_id,
            ]),
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'response' => null,
            'model' => $requestedModel,
            'llm_provider' => null,
            'llm_fallback' => false,
            'status' => 'pending',
            'error' => null,
            'ip' => $ip,
        ]);
        $this->transcript->attach($session, $log);

        $maxTokens = match ($mode) {
            self::MODE_EXPAND => 2200,
            self::MODE_CLARIFY => 1400,
            default => $this->looksMeaningless($question) ? 1200 : 1800,
        };
        $options = [
            'temperature' => 0.6,
            'max_tokens' => $maxTokens,
        ];

        $result = null;
        $text = '';
        $lightShadow = [];
        $need = null;
        $refineRequests = [];
        $rounds = 0;
        $pendingInput = $userPrompt;

        try {
            while (true) {
                $result = $this->complete($session, $systemPrompt, $requestedModel, $options, $log, $mode, $pendingInput, $agentExtra);
                $raw = trim((string) data_get($result->response->json(), 'choices.0.message.content', ''));
                if ($raw === '') {
                    $log->update([
                        'status' => 'error',
                        'error' => 'Empty model response',
                        'model' => $result->model,
                        'llm_provider' => $result->provider,
                        'llm_fallback' => $result->fallback,
                    ]);
                    throw new RuntimeException('Üres választ kaptunk az értelmezőtől.');
                }

                $parsed = $this->replies->parse($raw);
                $this->remember(
                    $session,
                    'assistant',
                    $parsed['status'] === 'need_data' ? 'data_request' : 'answer',
                    $raw,
                );

                $parsedNeed = $parsed['need'] ?? null;
                if ($parsed['status'] === 'need_data' || $parsedNeed !== null) {
                    $recovered = trim($parsed['text']);
                    if ($recovered !== '') {
                        $text = $recovered;
                        $lightShadow = is_array($parsed['light_shadow'] ?? null) ? $parsed['light_shadow'] : [];
                        $need = null;
                        break;
                    }
                    if ($rounds >= 1) {
                        $text = '';
                        $need = null;
                        break;
                    }

                    $rounds++;
                    $pendingInput = $locale === 'en'
                        ? 'Write the reading now as {"status":"answer","text":"..."}. Do not request more data.'
                        : 'Írd meg az olvasatot: {"status":"answer","text":"..."}. Több adatot ne kérj.';
                    $this->remember($session, 'user', 'data', $pendingInput);

                    continue;
                }

                $text = $parsed['text'];
                $lightShadow = is_array($parsed['light_shadow'] ?? null) ? $parsed['light_shadow'] : [];
                $need = null;
                break;
            }
        } catch (RuntimeException $e) {
            throw $e;
        }

        if ($text === '' && $need === null) {
            $log->update([
                'status' => 'error',
                'error' => 'Empty model response',
                'model' => $result?->model,
                'llm_provider' => $result?->provider,
                'llm_fallback' => (bool) ($result?->fallback),
            ]);
            throw new RuntimeException('Üres választ kaptunk az értelmezőtől.');
        }

        if ($mode === self::MODE_INITIAL) {
            $cards = $this->applyDecidedLightShadow($cards, $lightShadow);
            $session->update(['cards' => $cards]);
        } else {
            $lightShadow = [];
        }

        $log->update([
            'status' => 'success',
            'response' => $text,
            'cards' => $cards,
            'model' => $result?->model,
            'llm_provider' => $result?->provider,
            'llm_fallback' => (bool) ($result?->fallback),
            'error' => null,
            'astro' => array_merge($log->astro ?? [], [
                'data_rounds' => $rounds,
                'session_id' => $session->public_id,
            ]),
        ]);

        return [
            'text' => $text,
            'source' => $result?->toArray($locale) ?? [
                'provider' => '',
                'model' => '',
                'fallback' => false,
                'label' => '',
            ],
            'session_id' => $session->public_id,
            'light_shadow' => $lightShadow,
            'need' => $need,
            'requests' => $refineRequests,
        ];
    }

    private function withLightShadowProtocol(string $prompt, string $locale, array $cards): string
    {
        $needs = false;
        foreach ($cards as $card) {
            if (! empty($card['light_shadow_request'])) {
                $needs = true;
                break;
            }
        }
        if (! $needs || str_contains($prompt, 'light_shadow')) {
            return $prompt;
        }

        return rtrim($prompt)."\n".$this->settings->lightShadowProtocol($locale);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array<string, mixed>>  $decisions
     * @return list<array<string, mixed>>
     */
    private function applyDecidedLightShadow(array $cards, array $decisions): array
    {
        foreach ($decisions as $decision) {
            if (! is_array($decision)) {
                continue;
            }
            $index = $this->cardIndexForLightShadow($cards, $decision);
            if ($index === null || empty($cards[$index]['light_shadow_request'])) {
                continue;
            }
            $value = (string) ($decision['value'] ?? '');
            if (! in_array($value, ['light', 'shadow'], true)) {
                continue;
            }
            $cards[$index]['light_shadow'] = $value;
            $cards[$index]['polarity'] = $value === 'shadow' ? 'shadow' : 'positive';
            $cards[$index]['variant'] = $cards[$index]['polarity'];
        }

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  array<string, mixed>  $decision
     */
    private function cardIndexForLightShadow(array $cards, array $decision): ?int
    {
        if (is_numeric($decision['slot'] ?? null)) {
            $index = (int) $decision['slot'] - 1;
            if ($index >= 0 && $index < count($cards)) {
                return $index;
            }
        }

        $row = is_numeric($decision['row'] ?? null) ? (int) $decision['row'] : 0;
        $place = is_numeric($decision['place'] ?? null) ? (int) $decision['place'] : 0;
        if ($row < 1 || $place < 1) {
            return null;
        }

        foreach ($cards as $index => $card) {
            if ((int) ($card['row'] ?? 0) === $row && (int) ($card['place'] ?? 0) === $place) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $cards
     */
    private function session(array $payload, ?User $reader, string $locale, string $question, array $cards): ReadingSession
    {
        $mode = $this->normalizeMode((string) ($payload['mode'] ?? self::MODE_INITIAL));
        $publicId = trim((string) ($payload['session_id'] ?? ''));

        if ($publicId !== '') {
            $session = ReadingSession::query()->where('public_id', $publicId)->first();
            if ($session === null) {
                throw new RuntimeException($locale === 'en'
                    ? 'This reading session was not found.'
                    : 'Ez a kifejtés nem található.');
            }
            if ($session->user_id !== null && (int) $session->user_id !== (int) ($reader?->id ?? 0)) {
                throw new RuntimeException($locale === 'en'
                    ? 'This reading belongs to another account.'
                    : 'Ez a kifejtés egy másik fiókhoz tartozik.');
            }
            if ($mode === self::MODE_INITIAL) {
                $session->update([
                    'game_id' => isset($payload['game_id']) ? (int) $payload['game_id'] : $session->game_id,
                    'locale' => $locale,
                    'question' => mb_substr($question, 0, 280),
                    'cards' => $cards,
                ]);
            }

            return $session;
        }

        return ReadingSession::query()->create([
            'user_id' => $reader?->id,
            'game_id' => isset($payload['game_id']) ? (int) $payload['game_id'] : null,
            'locale' => $locale,
            'question' => mb_substr($question, 0, 280),
            'cards' => $cards,
        ]);
    }

    private function remember(ReadingSession $session, string $role, string $kind, string $content): void
    {
        $next = (int) $session->turns()->max('sequence') + 1;
        $session->turns()->create([
            'sequence' => $next,
            'role' => $role,
            'kind' => $kind,
            'content' => $content,
        ]);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function messages(ReadingSession $session, string $systemPrompt): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($session->turns()->orderBy('sequence')->get() as $turn) {
            if (! in_array($turn->role, ['user', 'assistant'], true) || (string) $turn->kind === 'classify') {
                continue;
            }
            $messages[] = [
                'role' => $turn->role,
                'content' => $turn->content,
            ];
        }

        return $messages;
    }

    private function complete(
        ReadingSession $session,
        string $systemPrompt,
        string $requestedModel,
        array $options,
        ReadingLog $log,
        string $mode,
        string $pendingInput,
        string $agentExtra,
    ): \App\Services\LlmChatResult {
        try {
            $result = $this->shouldUseAgent($session)
                ? $this->agents->completeTurn($session, $pendingInput, $agentExtra)
                : $this->openAI->complete(
                    $this->messages($session, $systemPrompt),
                    $requestedModel,
                    $options,
                );
        } catch (Throwable $e) {
            Log::error('Tarot reading OpenAI request failed', ['error' => $e->getMessage(), 'mode' => $mode]);
            $log->update([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Az értelmezés most nem elérhető. Próbáld újra később.');
        }

        if (! $result->response->successful()) {
            $errorBody = $result->response->json();
            Log::error('Tarot reading OpenAI error', [
                'status' => $result->response->status(),
                'body' => $errorBody,
                'mode' => $mode,
            ]);
            $log->update([
                'status' => 'error',
                'error' => 'HTTP '.$result->response->status().': '.json_encode($errorBody, JSON_UNESCAPED_UNICODE),
            ]);
            throw new RuntimeException('Az értelmezés most nem elérhető. Próbáld újra később.');
        }

        return $result;
    }

    private function shouldUseAgent(ReadingSession $session): bool
    {
        if (filled($session->category) && $session->category !== Game::SPREAD_NA) {
            return false;
        }

        if (! $this->agents->enabled()) {
            return false;
        }

        if (filled($session->llm_session_id)) {
            return true;
        }

        return $session->turns()->count() <= 1;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    private function agentSessionInstructions(string $locale, string $spreadPrompt, array $cards): string
    {
        $parts = [];
        $spreadPrompt = trim($spreadPrompt);
        if ($spreadPrompt !== '') {
            $parts[] = ($locale === 'en' ? '## SPREAD' : '## KIRAKASPROMPT')."\n".$spreadPrompt;
        }

        $plain = $this->settings->systemPrompt($locale);
        $withLightShadow = $this->withLightShadowProtocol($plain, $locale, $cards);
        if ($withLightShadow !== $plain) {
            $parts[] = trim(substr($withLightShadow, strlen(rtrim($plain))));
        }

        return trim(implode("\n\n", array_filter($parts, fn ($part) => $part !== '')));
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, [self::MODE_INITIAL, self::MODE_CLARIFY, self::MODE_EXPAND, self::MODE_REFINE], true)
            ? $mode
            : self::MODE_INITIAL;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  array<string, mixed>  $astro
     */
    private function openingMessage(
        string $locale,
        string $question,
        array $cards,
        array $astro,
        string $spreadPrompt,
        ?User $reader,
        ?Game $game,
        string $mode,
        string $followUp,
        string $previousReading,
        string $classifiedSky = '',
        bool $classified = false,
        string $category = '',
        string $dimension = '',
    ): string {
        $baseHeader = $locale === 'en' ? '## BASE' : '## ALAPPROMPT';
        $spreadHeader = $locale === 'en' ? '## SPREAD' : '## KIRAKASPROMPT';
        $dataHeader = $locale === 'en' ? '## SPREAD_DATA' : '## KIRAKAS';
        $userHeader = $locale === 'en' ? '## USER' : '## FELHASZNALO';
        $emptySpread = $locale === 'en'
            ? 'No extra spread instructions.'
            : 'Nincs külön kirakásprompt.';

        $parts = [
            $baseHeader,
            $this->settings->basePrompt($locale),
            '',
            $spreadHeader,
            $spreadPrompt !== '' ? $spreadPrompt : $emptySpread,
            '',
            $dataHeader,
            $locale === 'en' ? 'Drawn cards:' : 'Húzott lapok:',
            $this->formatCards($locale, $cards, $classified),
            '',
            $this->spreadTypeBlock($locale, $game, $category, $dimension),
            $mode === self::MODE_INITIAL
                ? ($classified
                    ? ($classifiedSky !== ''
                        ? $classifiedSky
                        : ($locale === 'en'
                            ? 'This spread sends no extra sky data.'
                            : 'Ehhez a kirakáshoz nincs külön égi adat.'))
                    : $this->firstRoundNote($locale))
                : $this->astrologyBlock($locale, $astro, $reader, $game),
            '',
            $userHeader,
            $this->userBlock($locale, $question, $reader),
        ];

        if ($mode !== self::MODE_INITIAL || $previousReading !== '' || $followUp !== '') {
            $parts[] = '';
            $parts[] = $this->turnMessage($locale, $mode, $followUp, $previousReading);
        }

        return implode("\n", $parts);
    }

    /**
     * Cards and the question. The standing prompts stay on the saved agent.
     * The first round carries card catalog text and no astrology chart.
     *
     * @param  list<array<string, mixed>>  $cards
     * @param  array<string, mixed>  $astro
     */
    private function variableMessage(
        string $locale,
        string $question,
        array $cards,
        array $astro,
        string $spreadPrompt,
        ?User $reader,
        ?Game $game,
        string $mode,
        string $followUp,
        string $previousReading,
        string $category = '',
        string $dimension = '',
    ): string {
        $spreadHeader = $locale === 'en' ? '## SPREAD' : '## KIRAKASPROMPT';
        $dataHeader = $locale === 'en' ? '## SPREAD_DATA' : '## KIRAKAS';
        $emptySpread = $locale === 'en'
            ? 'No extra spread instructions.'
            : 'Nincs külön kirakásprompt.';
        $userHeader = $locale === 'en' ? '## USER' : '## FELHASZNALO';
        $parts = [
            $spreadHeader,
            $spreadPrompt !== '' ? $spreadPrompt : $emptySpread,
            '',
            $dataHeader,
            $locale === 'en' ? 'Drawn cards:' : 'Húzott lapok:',
            $this->formatCards($locale, $cards),
            '',
            $this->spreadTypeBlock($locale, $game, $category, $dimension),
            $mode === self::MODE_INITIAL
                ? $this->firstRoundNote($locale)
                : $this->astrologyBlock($locale, $astro, $reader, $game),
            '',
            $userHeader,
            $this->userBlock($locale, $question, $reader),
        ];

        if ($mode !== self::MODE_INITIAL || $previousReading !== '' || $followUp !== '') {
            $parts[] = '';
            $parts[] = $this->turnMessage($locale, $mode, $followUp, $previousReading);
        }

        return implode("\n", $parts);
    }

    private function userBlock(string $locale, string $question, ?User $reader): string
    {
        $lines = [];
        $lines[] = $locale === 'en' ? 'Question:' : 'Kérdés:';
        $lines[] = $question;

        if ($reader?->birth_at !== null) {
            $local = $reader->birth_at->copy()->timezone('Europe/Budapest');
            $lines[] = $locale === 'en'
                ? 'Birth time (Europe/Budapest): '.$local->format('Y-m-d H:i')
                : 'Születési idő (Europe/Budapest): '.$local->format('Y-m-d H:i');
        }

        $birthPlace = $this->placeLine($locale, 'birth', $reader);
        if ($birthPlace !== '') {
            $lines[] = $birthPlace;
        }

        $residence = $this->placeLine($locale, 'residence', $reader);
        if ($residence !== '') {
            $lines[] = $residence;
        }

        $gender = $this->genderLabel($locale, (string) ($reader->gender ?? ''));
        if ($gender !== '') {
            $lines[] = ($locale === 'en' ? 'Gender: ' : 'Nem: ').$gender;
        }

        $introduction = trim((string) ($reader->introduction ?? ''));
        if ($introduction !== '') {
            $lines[] = ($locale === 'en' ? 'Introduction: ' : 'Bemutatkozás: ').$introduction;
        }

        return implode("\n", $lines);
    }

    private function placeLine(string $locale, string $prefix, ?User $reader): string
    {
        if ($reader === null) {
            return '';
        }
        $country = trim((string) $reader->{$prefix.'_country'});
        $city = trim((string) $reader->{$prefix.'_city'});
        if ($country === '' && $city === '') {
            return '';
        }
        $place = trim($city.($city !== '' && $country !== '' ? ', ' : '').$country);
        $lat = $reader->{$prefix.'_latitude'};
        $lon = $reader->{$prefix.'_longitude'};
        $coords = ($lat !== null && $lon !== null) ? ' ('.$lat.', '.$lon.')' : '';
        $label = $prefix === 'birth'
            ? ($locale === 'en' ? 'Birth place: ' : 'Születési hely: ')
            : ($locale === 'en' ? 'Residence: ' : 'Tartózkodási hely: ');

        return $label.$place.$coords;
    }

    private function genderLabel(string $locale, string $gender): string
    {
        return match ($gender) {
            'female' => $locale === 'en' ? 'female' : 'nő',
            'male' => $locale === 'en' ? 'male' : 'férfi',
            'other' => $locale === 'en' ? 'other' : 'egyéb',
            default => '',
        };
    }

    private function turnMessage(string $locale, string $mode, string $followUp, string $previousReading): string
    {
        $lines = [];
        $lines[] = $locale === 'en' ? '## TURN' : '## FORDULO';
        if ($mode === self::MODE_CLARIFY) {
            $lines[] = $locale === 'en'
                ? 'This is a clarification of the same reading, not a new question.'
                : 'Ez ugyanannak a kifejtésnek a pontosítása, nem új kérdés.';
            $lines[] = $followUp === ''
                ? ($locale === 'en'
                    ? 'Clarification: empty. Give a general reading of this same spread.'
                    : 'Pontosítás: üres. Általános kifejtést kér ugyanerről a kirakásról.')
                : ($locale === 'en' ? 'Clarification: ' : 'Pontosítás: ').$followUp;
        } elseif ($mode === self::MODE_EXPAND) {
            $lines[] = $locale === 'en'
                ? 'This is a deeper expansion of the same reading, not a new question.'
                : 'Ez ugyanannak a kifejtésnek a bővebb kifejtése, nem új kérdés.';
            if ($followUp !== '') {
                $lines[] = ($locale === 'en' ? 'Optional focus: ' : 'Opcionális fókusz: ').$followUp;
            }
        } else {
            $lines[] = $locale === 'en'
                ? 'Write the first reading for this question.'
                : 'Írd meg ennek a kérdésnek az első olvasatát.';
        }

        if ($previousReading !== '') {
            $lines[] = '';
            $lines[] = $locale === 'en' ? 'Previous reading:' : 'Korábbi olvasat:';
            $lines[] = $previousReading;
        }

        return implode("\n", $lines);
    }

    /**
     * Detect keyboard mash / nonsense character strings posing as a question.
     */
    public function looksMeaningless(string $question): bool
    {
        $q = trim($question);
        if ($q === '') {
            return false;
        }

        $compact = preg_replace('/\s+/u', '', $q) ?? '';
        if (mb_strlen($compact) < 8) {
            return false;
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $q) ?? '';
        $letterLen = mb_strlen($letters);

        if ($letterLen < 6) {
            return true;
        }

        $vowelMatches = preg_match_all('/[aeiouyáéíóöőúüűAEIOUYÁÉÍÓÖŐÚÜŰ]/u', $letters);
        $vowelCount = is_int($vowelMatches) ? $vowelMatches : 0;
        $vowelRatio = $vowelCount / $letterLen;

        $chars = preg_split('//u', mb_strtolower($letters), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $unique = count(array_unique($chars));

        $words = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        if ($letterLen >= 10 && $vowelRatio < 0.18) {
            return true;
        }

        if ($letterLen >= 12 && $unique <= 4) {
            return true;
        }

        // Long consonant runs are typical of keyboard mash, rare in normal questions.
        if ($letterLen >= 10 && preg_match('/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]{4,}/u', $letters) === 1) {
            return true;
        }

        if ($wordCount === 1 && $letterLen >= 12 && $vowelRatio < 0.28) {
            return true;
        }

        if ($wordCount === 1 && $letterLen >= 14 && $this->looksLikeKeyboardWalk($letters)) {
            return true;
        }

        return false;
    }

    private function looksLikeKeyboardWalk(string $letters): bool
    {
        $lower = mb_strtolower($letters);
        $rows = [
            'qwertyuiop',
            'asdfghjkl',
            'zxcvbnm',
            'poiuytrewq',
            'lkjhgfdsa',
            'mnbvcxz',
        ];

        foreach ($rows as $row) {
            if (mb_strpos($row.$row, $lower) !== false || mb_strpos($lower, $row) !== false) {
                return true;
            }
            // Any 5+ character substring that sits on one keyboard row.
            $len = mb_strlen($lower);
            for ($i = 0; $i <= $len - 5; $i++) {
                $chunk = mb_substr($lower, $i, 5);
                if (mb_strpos($row, $chunk) !== false || mb_strpos(strrev($row), $chunk) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    private function formatCards(string $locale, array $cards, bool $omitExtraAstro = false): string
    {
        $blocks = [];
        foreach ($cards as $index => $card) {
            $row = max(1, (int) ($card['row'] ?? 1));
            $place = max(1, (int) ($card['place'] ?? ($index + 1)));
            $placeLabel = $locale === 'en'
                ? sprintf('Row %d, card %d', $row, $place)
                : sprintf('%d. sor %d. lap', $row, $place);
            $title = trim((string) ($card['slot_title'] ?? ''));
            $header = $title !== '' ? $placeLabel.' — '.$title : $placeLabel;

            $polarity = (string) ($card['polarity'] ?? $card['variant'] ?? 'positive');
            $infoMode = (string) ($card['info_mode'] ?? '');
            $figure = trim((string) ($card['figure_name'] ?? $card['figure_id'] ?? ''));
            $figureId = trim((string) ($card['figure_id'] ?? ''));

            $lines = [
                $header,
                ($locale === 'en' ? 'slot: ' : 'lap sorszám: ').($index + 1),
                ($locale === 'en' ? 'pack: ' : 'pakli: ').(string) ($card['deck'] ?? ''),
                ($locale === 'en' ? 'figure: ' : 'figura: ').$figure.($figureId !== '' ? ' ('.$figureId.')' : ''),
                ...TarotCardBrief::lines($locale, $figureId),
            ];

            $keyword = trim((string) ($card['keyword'] ?? ''));
            if ($keyword !== '') {
                $lines[] = ($locale === 'en' ? 'keyword: ' : 'kulcsszó: ').$keyword;
            }

            $blurb = trim((string) ($card['blurb'] ?? ''));
            if ($blurb !== '' && ($infoMode !== 'astro' || $omitExtraAstro)) {
                $lines[] = ($locale === 'en' ? 'meaning: ' : 'jelentés: ').$blurb;
            }

            $lines[] = 'LightShadow:'.$this->lightShadowMark($card, $polarity);

            $astroCaption = trim((string) ($card['astro_caption'] ?? ''));
            $includeAstro = $infoMode === 'astro'
                || ($infoMode === '' && $astroCaption !== '' && in_array((string) ($card['deck'] ?? ''), ['oracle', 'egi-orakulum'], true));
            if (! $omitExtraAstro && $includeAstro && $astroCaption !== '') {
                $lines[] = ($locale === 'en' ? 'astro data: ' : 'asztrológiai adat: ').$astroCaption;
            }

            if (filter_var($card['include_celestial_helper'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $helper = (string) ($card['celestial_helper'] ?? '');
                if (CelestialOracleCatalog::known($helper)) {
                    $face = (string) ($card['celestial_polarity'] ?? '') === 'shadow' ? 'shadow' : 'light';
                    array_push($lines, ...$this->helperBrief->lines(
                        $helper,
                        $locale,
                        $face,
                        $omitExtraAstro ? null : $this->helperChart(),
                        ! $omitExtraAstro,
                    ));
                }
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function lightShadowMark(array $card, string $polarity): string
    {
        $slotMode = (string) ($card['light_shadow_mode'] ?? '');
        $requests = filter_var($card['light_shadow_request'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($slotMode === 'none') {
            return 'None';
        }
        if ($requests) {
            return 'Get';
        }

        return in_array($polarity, ['shadow', 'negative', 'negatív', 'korlátozó'], true) ? 'shadow' : 'light';
    }

    /**
     * @return array{objects?: list<array<string, mixed>>, aspects?: list<array<string, mixed>>}|null
     */
    private function helperChart(): ?array
    {
        if ($this->helperChartResolved) {
            return $this->helperChart;
        }

        $this->helperChartResolved = true;
        try {
            $profile = $this->drawProfile->forNow();
            $chart = $profile['chart'] ?? null;
            $this->helperChart = is_array($chart) ? $chart : null;
        } catch (Throwable $e) {
            $this->helperChart = null;
        }

        return $this->helperChart;
    }

    /** @var array<string, array<string, string>> */
    private const CATEGORY_GLOSS = [
        'hu' => [
            'BINARY' => 'eldöntendő kérdés, igen vagy nem',
            'CHOICE' => 'választás, A vagy B, esetleg C',
            'TIMING' => 'alkalmas időpont, mikor',
            'EXPLORATORY' => 'nyitott elemzés, mit, miért, hogyan',
            'FORECAST' => 'állapotfelmérés, mi várható',
            'ANALYSIS' => 'milyen vagyok, mi az életfeladatom',
            'RELATION' => 'helyzetem, hogyan viszonyulnak hozzám',
            'N/A' => 'nem besorolható',
        ],
        'en' => [
            'BINARY' => 'yes or no',
            'CHOICE' => 'a choice, A or B, possibly C',
            'TIMING' => 'when',
            'EXPLORATORY' => 'open analysis, what, why, how',
            'FORECAST' => 'what is ahead',
            'ANALYSIS' => 'what I am like, what my life task is',
            'RELATION' => 'my situation, how they relate to me',
            'N/A' => 'unclassified',
        ],
    ];

    /** @var array<string, array<string, string>> */
    private const DIMENSION_GLOSS = [
        'hu' => [
            'REL' => 'párkapcsolat, házasság, társulás',
            'SOC' => 'barátság, közösség, család, hálózat',
            'CAR' => 'karrier, hivatás, státusz, munka',
            'FIN' => 'pénzügyek, vagyon, befektetés',
            'HLT' => 'egészség, vitalitás, mentális állapot',
            'LEG' => 'jogi ügyek, szerződések, konfliktus',
            'SPL' => 'önismeret, spiritualitás, életút',
            'N/A' => 'nem besorolható',
        ],
        'en' => [
            'REL' => 'relationship, marriage, partnership',
            'SOC' => 'friendship, community, family, network',
            'CAR' => 'career, vocation, status, work',
            'FIN' => 'finances, wealth, investment',
            'HLT' => 'health, vitality, mental state',
            'LEG' => 'legal matters, contracts, conflict',
            'SPL' => 'self-knowledge, spirituality, life path',
            'N/A' => 'unclassified',
        ],
    ];

    private function spreadTypeBlock(string $locale, ?Game $game, string $category = '', string $dimension = ''): string
    {
        $locale = $locale === 'en' ? 'en' : 'hu';
        $category = trim($category);
        $dimension = trim($dimension);
        if ($category !== '') {
            $gloss = self::CATEGORY_GLOSS[$locale][$category] ?? $category;
            $lines = [
                $locale === 'en'
                    ? "Category: {$category} ({$gloss}). Keep spread_type as this."
                    : "Kategória: {$category} ({$gloss}). A spread_type ez maradjon.",
            ];
            if ($dimension !== '') {
                $dimGloss = self::DIMENSION_GLOSS[$locale][$dimension] ?? $dimension;
                $lines[] = $locale === 'en'
                    ? "Dimension: {$dimension} ({$dimGloss}). Keep the reading inside this."
                    : "Dimenzió: {$dimension} ({$dimGloss}). A kifejtés ezen belül maradjon.";
            }
            $answer = $this->settings->categoryAnswerBlock($locale, $category);
            if ($answer !== '') {
                $lines[] = '';
                $lines[] = $answer;
            }

            return implode("\n", $lines);
        }

        $lines = [];
        $types = $this->configuredCodes($game?->spread_types, Game::SPREAD_TYPES);
        if ($types !== []) {
            $list = $this->labeledCodes($types, $locale, self::CATEGORY_GLOSS);
            $lines[] = $locale === 'en'
                ? "Category: {$list}. spread_type must be one of these."
                : "Kategória: {$list}. A spread_type ezek egyike legyen.";
        }

        $dimensions = $this->configuredCodes($game?->spread_dimensions, Game::DIMENSIONS);
        if ($dimensions !== []) {
            $list = $this->labeledCodes($dimensions, $locale, self::DIMENSION_GLOSS);
            $lines[] = $locale === 'en'
                ? "Dimension: {$list}. Keep the reading inside one of these."
                : "Dimenzió: {$list}. A kifejtés ezek egyike maradjon.";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  mixed  $stored
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function configuredCodes(mixed $stored, array $allowed): array
    {
        $codes = is_array($stored) ? array_values($stored) : [];

        return array_values(array_filter(
            $codes,
            fn ($code): bool => is_string($code) && in_array($code, $allowed, true),
        ));
    }

    /**
     * @param  list<string>  $codes
     * @param  array<string, array<string, string>>  $gloss
     */
    private function labeledCodes(array $codes, string $locale, array $gloss): string
    {
        $bag = $gloss[$locale] ?? $gloss['hu'];

        return implode(', ', array_map(
            fn (string $code): string => $code.' ('.($bag[$code] ?? $code).')',
            $codes,
        ));
    }

    private function firstRoundNote(string $locale): string
    {
        return $locale === 'en'
            ? 'No astrology chart in this round. Card meanings are in the catalog lines under the drawn cards. Search the web for anything this message does not contain. Reply with status=answer. Do not request more data.'
            : 'Ebben a körben asztrológiai képlet nincs. A lapjelentés a húzott lapok katalógus soraiban van. Ami ebből az üzenetből hiányzik, azt keresd a neten. A válaszod status=answer. Adatot ne kérj.';
    }

    /**
     * @param  array<string, mixed>  $astro
     */
    private function astrologyBlock(string $locale, array $astro, ?User $reader, ?Game $game): string
    {
        if ($game === null) {
            return $this->formatScoring($locale, $astro);
        }

        $modes = $game->astroModes();

        return $locale === 'en'
            ? 'Continue from the cards and sky already in this dialogue'
                .($modes === [] ? '.' : ' ('.implode(', ', $modes).').')
                .' Do not request more data.'
            : 'Folytasd a párbeszédben már meglévő lapokból és égi adatból'
                .($modes === [] ? '.' : ' ('.implode(', ', $modes).').')
                .' Több adatot ne kérj.';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatSkyResult(string $locale, array $result): string
    {
        $error = (string) ($result['error'] ?? '');
        if ($error === 'relations_unknown') {
            return 'relation: none';
        }
        if ($error === 'birth_unknown') {
            return 'user: none';
        }
        if ($error !== '') {
            $id = (string) ($result['id'] ?? '');

            return $locale === 'en'
                ? 'Sky data unavailable ('.$id.').'
                : 'Égi adat nem elérhető ('.$id.').';
        }

        if ((string) ($result['kind'] ?? '') === 'projection' || (string) ($result['moment'] ?? '') === 'projection') {
            $encoded = json_encode($result['data'] ?? [], JSON_UNESCAPED_UNICODE);

            return 'projection '.(string) ($result['id'] ?? '')."\n".(is_string($encoded) ? $encoded : '');
        }

        $lines = is_array($result['lines'] ?? null) ? $result['lines'] : null;
        if ($lines === null) {
            $planets = is_array($result['data']['planets'] ?? null) ? $result['data']['planets'] : [];
            $lines = (new ChartLineFormatter)->fromPresented($planets);
        }

        $id = (string) ($result['id'] ?? '');
        $label = match ($id) {
            'birth' => 'user:',
            'relations' => 'relation:',
            default => 'now:',
        };

        return $label."\npositions: ".(string) ($lines['positions'] ?? '')."\naspects: ".(string) ($lines['aspects'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function refineMessage(string $locale, array $payload, ?User $reader): string
    {
        $refine = is_array($payload['refine'] ?? null) ? $payload['refine'] : [];
        $need = (string) ($refine['need'] ?? '');
        $blocks = [];
        $blocks[] = $locale === 'en' ? '## DATA' : '## ADAT';
        $blocks[] = $locale === 'en'
            ? 'The seeker accepted one clarification. Use this and answer. Do not ask for more data.'
            : 'A kérdező egy pontosítást elfogadott. Ebből válaszolj. Ne kérj további adatot.';

        if ($need === 'birth') {
            $blocks[] = $this->suppliedChart($locale, 'user:', $refine, 'birth', $reader);
        }
        if ($need === 'relation') {
            $blocks[] = $this->suppliedChart($locale, 'relation:', $refine, 'relation', $reader);
        }

        $requests = is_array($refine['requests'] ?? null) ? $refine['requests'] : [];
        if ($requests !== []) {
            foreach ($this->sky->fulfill($requests, $reader) as $result) {
                $blocks[] = $this->formatSkyResult($locale, $result);
            }
        }

        return implode("\n", $blocks);
    }

    /**
     * @param  array<string, mixed>  $refine
     */
    private function suppliedChart(string $locale, string $label, array $refine, string $prefix, ?User $reader): string
    {
        $when = trim((string) ($refine[$prefix.'_at'] ?? ''));
        $country = trim((string) ($refine[$prefix.'_country'] ?? ''));
        $city = trim((string) ($refine[$prefix.'_city'] ?? ''));
        $gender = $this->genderLabel($locale, (string) ($refine[$prefix === 'birth' ? 'gender' : 'relation_gender'] ?? ''));
        $intro = trim((string) ($refine[$prefix === 'birth' ? 'introduction' : 'relation_introduction'] ?? ''));
        if ($when === '') {
            return $label."\nnone";
        }

        try {
            $utc = \Carbon\Carbon::parse($when, 'Europe/Budapest')->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable) {
            return $label."\nnone";
        }

        $coords = $this->places->coordinates($country, $city);
        if ($coords === null) {
            $fallback = $this->sky->chartCoordinates($prefix === 'birth' ? 'birth' : 'projection', $reader);
            $lat = $fallback['lat'];
            $lon = $fallback['lon'];
        } else {
            $lat = $coords['latitude'];
            $lon = $coords['longitude'];
        }

        try {
            $lines = $this->sky->chartLines($utc, $lat, $lon);
        } catch (\Throwable) {
            return $label."\nnone";
        }

        $text = $label."\npositions: ".$lines['positions']."\naspects: ".$lines['aspects'];
        if ($gender !== '') {
            $text .= "\n".($locale === 'en' ? 'Gender: ' : 'Nem: ').$gender;
        }
        if ($intro !== '') {
            $text .= "\n".($locale === 'en' ? 'Introduction: ' : 'Bemutatkozás: ').$intro;
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $astro
     */
    private function formatScoring(string $locale, array $astro): string
    {
        $scoring = is_array($astro['scoring'] ?? null) ? $astro['scoring'] : [];
        $element = (string) ($scoring['element_name'] ?? $astro['element'] ?? '');
        $modality = (string) ($scoring['modality_name'] ?? $astro['modality'] ?? '');
        $tone = (string) ($scoring['tone_name'] ?? $astro['tone'] ?? '');
        $datetime = (string) ($astro['datetime_utc'] ?? '');
        $sun = (string) ($astro['sun_sign'] ?? '');

        $lines = [];
        $lines[] = $locale === 'en' ? 'Moment scoring:' : 'Az adott időpont pontozása:';
        if ($datetime !== '') {
            $lines[] = ($locale === 'en' ? 'datetime UTC: ' : 'időpont (UTC): ').$datetime;
        }
        if ($sun !== '') {
            $lines[] = ($locale === 'en' ? 'sun sign: ' : 'napjegy: ').$sun;
        }

        $elementValue = $scoring['element_value'] ?? null;
        $elementLine = $locale === 'en' ? 'element: ' : 'elem: ';
        $elementLine .= $element !== '' ? $element : '—';
        if ($this->isPresentNumber($elementValue)) {
            $elementLine .= ' ('.$this->formatScoreNumber($elementValue).')';
        }
        $lines[] = $elementLine;

        $modalityValue = $scoring['modality_value'] ?? null;
        $modalityLine = $locale === 'en' ? 'quality: ' : 'minőség: ';
        $modalityLine .= $modality !== '' ? $modality : '—';
        if ($this->isPresentNumber($modalityValue)) {
            $modalityLine .= ' ('.$this->formatScoreNumber($modalityValue).')';
        }
        $lines[] = $modalityLine;

        $tonePositive = $scoring['tone_positive'] ?? null;
        $toneNegative = $scoring['tone_negative'] ?? null;
        $toneNet = $scoring['tone_net'] ?? null;
        $toneLine = $locale === 'en' ? 'positive / negative: ' : 'pozitív / negatív: ';
        $toneLine .= $tone !== '' ? $tone : '—';
        $toneBits = [];
        if ($this->isPresentNumber($tonePositive)) {
            $toneBits[] = ($locale === 'en' ? 'positive ' : 'pozitív ').$this->formatScoreNumber($tonePositive);
        }
        if ($this->isPresentNumber($toneNegative)) {
            $toneBits[] = ($locale === 'en' ? 'negative ' : 'negatív ').$this->formatScoreNumber($toneNegative);
        }
        if ($this->isPresentNumber($toneNet)) {
            $toneBits[] = ($locale === 'en' ? 'net ' : 'nettó ').$this->formatScoreNumber($toneNet);
        }
        if ($toneBits !== []) {
            $toneLine .= ' ('.implode(', ', $toneBits).')';
        }
        $lines[] = $toneLine;

        $elements = is_array($scoring['elements'] ?? null) ? $scoring['elements'] : [];
        if ($elements !== []) {
            $lines[] = ($locale === 'en' ? 'elements: ' : 'elemek: ').$this->formatScoreMap($elements);
        }
        $modalities = is_array($scoring['modalities'] ?? null) ? $scoring['modalities'] : [];
        if ($modalities !== []) {
            $lines[] = ($locale === 'en' ? 'qualities: ' : 'minőségek: ').$this->formatScoreMap($modalities);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function formatScoreMap(array $map): string
    {
        $parts = [];
        foreach ($map as $name => $value) {
            $parts[] = $name.' '.$this->formatScoreNumber($value);
        }

        return implode(', ', $parts);
    }

    private function formatScoreNumber(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }
        $number = (float) $value;
        if (abs($number - round($number)) < 0.001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function isPresentNumber(mixed $value): bool
    {
        return is_numeric($value);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    private function enrichCards(array $cards, string $locale): array
    {
        foreach ($cards as &$card) {
            $card = $this->meanings->overlayCard($card, $locale);
            if (($card['info_mode'] ?? '') !== 'meaning') {
                continue;
            }
            if (trim((string) ($card['blurb'] ?? '')) !== '') {
                continue;
            }
            $card['blurb'] = $this->meanings->text(
                (string) ($card['deck'] ?? ''),
                (string) ($card['figure_id'] ?? ''),
                $locale,
                (string) ($card['figure_name'] ?? ''),
            );
        }
        unset($card);

        return $cards;
    }
}
