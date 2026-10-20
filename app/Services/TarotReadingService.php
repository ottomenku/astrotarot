<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TarotReadingService
{
    public const MODE_INITIAL = 'initial';

    public const MODE_CLARIFY = 'clarify';

    public const MODE_EXPAND = 'expand';

    public const MAX_DATA_ROUNDS = 3;

    public function __construct(
        private readonly OpenAIClient $openAI,
        private readonly AppSettings $settings,
        private readonly FigureMeaningService $meanings,
        private readonly ReadingReplyParser $replies,
        private readonly ReadingSkyLookup $sky,
    ) {}

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
     * @return array{text: string, source: array{provider: string, model: string, fallback: bool, label: string}}
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

        if ($mode !== self::MODE_INITIAL && $previousReading === '') {
            throw new RuntimeException('A follow-up requires the previous reading.');
        }

        if ($mode === self::MODE_CLARIFY && $followUp === '') {
            throw new RuntimeException('A clarification requires a follow-up message.');
        }

        $systemPrompt = $this->settings->systemPrompt($locale);
        $spreadPrompt = $overridePrompt;
        $reader = $userId ? User::query()->find($userId) : null;
        $session = $this->session($payload, $reader, $locale, $question, $cards);
        $continuing = $mode !== self::MODE_INITIAL && $session->turns()->exists();

        $userPrompt = $continuing
            ? $this->turnMessage($locale, $mode, $followUp, $previousReading)
            : $this->openingMessage($locale, $question, $cards, $astro, $spreadPrompt, $reader, $mode, $followUp, $previousReading);

        $this->remember($session, 'user', $continuing ? $mode : 'opening', $userPrompt);

        $logQuestion = $mode === self::MODE_CLARIFY
            ? $question."\n\n[pontosítás] ".$followUp
            : ($mode === self::MODE_EXPAND ? $question."\n\n[bővebb kifejtés]" : $question);

        $log = ReadingLog::query()->create([
            'user_id' => $userId,
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
        $rounds = 0;

        try {
            while (true) {
                $result = $this->complete($session, $systemPrompt, $requestedModel, $options, $log, $mode);
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

                if ($parsed['status'] !== 'need_data') {
                    $text = $parsed['text'];
                    break;
                }

                if ($rounds >= self::MAX_DATA_ROUNDS) {
                    $text = $parsed['text'];
                    break;
                }

                $rounds++;
                $data = $this->sky->fulfill($parsed['requests'], $reader);
                $this->remember($session, 'user', 'data', $this->dataMessage($locale, $data, $rounds));
            }
        } catch (RuntimeException $e) {
            throw $e;
        }

        if ($text === '') {
            $log->update([
                'status' => 'error',
                'error' => 'Empty model response',
                'model' => $result?->model,
                'llm_provider' => $result?->provider,
                'llm_fallback' => (bool) ($result?->fallback),
            ]);
            throw new RuntimeException('Üres választ kaptunk az értelmezőtől.');
        }

        $log->update([
            'status' => 'success',
            'response' => $text,
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
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $cards
     */
    private function session(array $payload, ?User $reader, string $locale, string $question, array $cards): ReadingSession
    {
        $mode = $this->normalizeMode((string) ($payload['mode'] ?? self::MODE_INITIAL));
        $publicId = trim((string) ($payload['session_id'] ?? ''));

        if ($mode !== self::MODE_INITIAL && $publicId !== '') {
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
            if (! in_array($turn->role, ['user', 'assistant'], true)) {
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
    ): \App\Services\LlmChatResult {
        try {
            $result = $this->openAI->complete(
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

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, [self::MODE_INITIAL, self::MODE_CLARIFY, self::MODE_EXPAND], true)
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
        string $mode,
        string $followUp,
        string $previousReading,
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
            $this->formatCards($locale, $cards),
            '',
            $this->formatScoring($locale, $astro),
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

        $gender = $this->genderLabel($locale, (string) ($reader->gender ?? ''));
        if ($gender !== '') {
            $lines[] = ($locale === 'en' ? 'Gender: ' : 'Nem: ').$gender;
        }

        $introduction = trim((string) ($reader->introduction ?? ''));
        if ($introduction !== '') {
            $lines[] = ($locale === 'en' ? 'Introduction: ' : 'Bemutatkozás: ').$introduction;
        }

        if ($this->looksMeaningless($question)) {
            $lines[] = '';
            $lines[] = $locale === 'en'
                ? 'SPECIAL CASE: The question above is gibberish / uninterpretable. Do not give a normal reading and do not request sky data. Reply that Your Master cannot interpret the question, then send a short random poem they feel the seeker needs.'
                : 'KÜLÖNLEGES ESET: A fenti „kérdés” értelmetlen karakterhalmaz / nem értelmezhető. Ne adj szokásos olvasatot, és ne kérj égi adatot. Írd meg, hogy a Mestered a kérdést nem tudja értelmezni, majd küldj egy rövid, véletlenszerű verset, amire szerinte szükség van.';
        }

        return implode("\n", $lines);
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
            $lines[] = ($locale === 'en' ? 'Clarification: ' : 'Pontosítás: ').$followUp;
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
     * @param  list<array<string, mixed>>  $results
     */
    private function dataMessage(string $locale, array $results, int $round): string
    {
        $remaining = max(0, self::MAX_DATA_ROUNDS - $round);
        $payload = [
            'round' => $round,
            'remaining_requests' => $remaining,
            'results' => $results,
        ];
        if ($remaining === 0) {
            $payload['note'] = $locale === 'en'
                ? 'No further data will be fetched. The next reply must be status=answer.'
                : 'További adat nem érkezik. A következő válasz status=answer legyen.';
        }

        $header = $locale === 'en' ? '## DATA' : '## ADAT';

        return $header."\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
    private function formatCards(string $locale, array $cards): string
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
            $tone = in_array($polarity, ['shadow', 'negative', 'negatív', 'korlátozó'], true)
                ? ($locale === 'en' ? 'restrictive / shadow' : 'korlátozó / árnyék')
                : ($locale === 'en' ? 'supportive / light' : 'támogató / light');
            $infoMode = (string) ($card['info_mode'] ?? '');
            $figure = trim((string) ($card['figure_name'] ?? $card['figure_id'] ?? ''));
            $figureId = trim((string) ($card['figure_id'] ?? ''));

            $lines = [
                $header,
                ($locale === 'en' ? 'pack: ' : 'pakli: ').(string) ($card['deck'] ?? ''),
                ($locale === 'en' ? 'figure: ' : 'figura: ').$figure.($figureId !== '' ? ' ('.$figureId.')' : ''),
            ];

            $keyword = trim((string) ($card['keyword'] ?? ''));
            if ($keyword !== '') {
                $lines[] = ($locale === 'en' ? 'keyword: ' : 'kulcsszó: ').$keyword;
            }

            $blurb = trim((string) ($card['blurb'] ?? ''));
            if ($blurb !== '' && $infoMode !== 'astro') {
                $lines[] = ($locale === 'en' ? 'meaning: ' : 'jelentés: ').$blurb;
            }

            $lines[] = ($locale === 'en' ? 'polarity: ' : 'polaritás: ').$polarity.' ('.$tone.')';

            $astroCaption = trim((string) ($card['astro_caption'] ?? ''));
            $includeAstro = $infoMode === 'astro'
                || ($infoMode === '' && $astroCaption !== '' && in_array((string) ($card['deck'] ?? ''), ['oracle', 'egi-orakulum'], true));
            if ($includeAstro && $astroCaption !== '') {
                $lines[] = ($locale === 'en' ? 'astro data: ' : 'asztrológiai adat: ').$astroCaption;
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
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
