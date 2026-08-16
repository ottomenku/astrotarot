<?php

namespace App\Services;

use App\Models\ReadingLog;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TarotReadingService
{
    public const MODE_INITIAL = 'initial';

    public const MODE_CLARIFY = 'clarify';

    public const MODE_EXPAND = 'expand';

    public function __construct(
        private readonly OpenAIClient $openAI,
        private readonly AppSettings $settings,
    ) {
    }

    /**
     * @param  array{
     *   question: string,
     *   locale?: string,
     *   cards: list<array<string, mixed>>,
     *   astro?: array<string, mixed>,
     *   mode?: string,
     *   previous_reading?: string|null,
     *   follow_up?: string|null,
     *   ip?: string|null
     * }  $payload
     */
    public function interpret(array $payload): string
    {
        $locale = ($payload['locale'] ?? 'hu') === 'en' ? 'en' : 'hu';
        $question = trim((string) ($payload['question'] ?? ''));
        $cards = array_values($payload['cards'] ?? []);
        $astro = is_array($payload['astro'] ?? null) ? $payload['astro'] : [];
        $ip = isset($payload['ip']) ? (string) $payload['ip'] : null;
        $mode = $this->normalizeMode((string) ($payload['mode'] ?? self::MODE_INITIAL));
        $previousReading = trim((string) ($payload['previous_reading'] ?? ''));
        $followUp = trim((string) ($payload['follow_up'] ?? ''));
        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        if ($question === '' || count($cards) !== 2) {
            throw new RuntimeException('A reading requires a question and exactly 2 cards.');
        }

        if ($mode !== self::MODE_INITIAL && $previousReading === '') {
            throw new RuntimeException('A follow-up requires the previous reading.');
        }

        if ($mode === self::MODE_CLARIFY && $followUp === '') {
            throw new RuntimeException('A clarification requires a follow-up message.');
        }

        $systemPrompt = match ($mode) {
            self::MODE_CLARIFY => $this->settings->clarifyPrompt($locale),
            self::MODE_EXPAND => $this->settings->expandPrompt($locale),
            default => $this->settings->systemPrompt($locale),
        };

        $userPrompt = match ($mode) {
            self::MODE_CLARIFY, self::MODE_EXPAND => $this->followUpUserPrompt(
                $locale,
                $mode,
                $question,
                $cards,
                $astro,
                $previousReading,
                $followUp
            ),
            default => $this->userPrompt($locale, $question, $cards, $astro),
        };

        $logQuestion = $mode === self::MODE_CLARIFY
            ? $question."\n\n[pontosítás] ".$followUp
            : ($mode === self::MODE_EXPAND ? $question."\n\n[bővebb kifejtés]" : $question);

        $log = ReadingLog::query()->create([
            'locale' => $locale,
            'question' => $logQuestion,
            'cards' => $cards,
            'astro' => array_merge($astro, [
                'mode' => $mode,
                'follow_up' => $followUp !== '' ? $followUp : null,
            ]),
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'response' => null,
            'model' => $model,
            'status' => 'pending',
            'error' => null,
            'ip' => $ip,
        ]);

        $maxTokens = match ($mode) {
            self::MODE_EXPAND => 2200,
            self::MODE_CLARIFY => 1400,
            default => 1600,
        };

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], $model, [
                'temperature' => $mode === self::MODE_EXPAND ? 0.8 : 0.85,
                'max_tokens' => $maxTokens,
            ]);
        } catch (Throwable $e) {
            Log::error('Tarot reading OpenAI request failed', ['error' => $e->getMessage(), 'mode' => $mode]);
            $log->update([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Az értelmezés most nem elérhető. Próbáld újra később.');
        }

        if (! $response->successful()) {
            $errorBody = $response->json();
            Log::error('Tarot reading OpenAI error', [
                'status' => $response->status(),
                'body' => $errorBody,
                'mode' => $mode,
            ]);
            $log->update([
                'status' => 'error',
                'error' => 'HTTP '.$response->status().': '.json_encode($errorBody, JSON_UNESCAPED_UNICODE),
            ]);
            throw new RuntimeException('Az értelmezés most nem elérhető. Próbáld újra később.');
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($text === '') {
            $log->update([
                'status' => 'error',
                'error' => 'Empty model response',
            ]);
            throw new RuntimeException('Üres választ kaptunk az értelmezőtől.');
        }

        $log->update([
            'status' => 'success',
            'response' => $text,
            'error' => null,
        ]);

        return $text;
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
    private function userPrompt(string $locale, string $question, array $cards, array $astro): string
    {
        $lines = [];
        $lines[] = $locale === 'en' ? 'Question:' : 'Kérdés:';
        $lines[] = $question;
        $lines[] = '';
        $lines[] = $locale === 'en' ? 'Draw context:' : 'Húzás kontextusa:';
        $lines[] = json_encode([
            'sun_sign' => $astro['sun_sign'] ?? null,
            'element' => $astro['element'] ?? null,
            'modality' => $astro['modality'] ?? null,
            'datetime_utc' => $astro['datetime_utc'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lines[] = '';
        $lines[] = $locale === 'en' ? 'Cards:' : 'Lapok:';
        $lines[] = $this->formatCards($locale, $cards);
        $lines[] = '';
        $lines[] = $locale === 'en'
            ? 'Write the reading now: Celestial Master facts with meaning → chosen figure name/meaning (“interprets this as…”) → answer to the question. Max 10–12 sentences.'
            : 'Most írd meg az olvasatot: Égi mester adatai jelentéssel → választott figura neve/jelentése („úgy értelmezi, hogy…”) → válasz a kérdésre. Legfeljebb 10–12 mondat.';

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  array<string, mixed>  $astro
     */
    private function followUpUserPrompt(
        string $locale,
        string $mode,
        string $question,
        array $cards,
        array $astro,
        string $previousReading,
        string $followUp,
    ): string {
        $lines = [];
        $lines[] = $locale === 'en' ? 'Original question:' : 'Eredeti kérdés:';
        $lines[] = $question;
        $lines[] = '';
        $lines[] = $locale === 'en' ? 'Cards:' : 'Lapok:';
        $lines[] = $this->formatCards($locale, $cards);
        $lines[] = '';
        $lines[] = $locale === 'en' ? 'Previous reading:' : 'Korábbi olvasat:';
        $lines[] = $previousReading;
        $lines[] = '';

        if ($mode === self::MODE_CLARIFY) {
            $lines[] = $locale === 'en' ? 'Clarification request:' : 'Pontosítás:';
            $lines[] = $followUp;
            $lines[] = '';
            $lines[] = $locale === 'en'
                ? 'Answer the clarification now.'
                : 'Most válaszolj a pontosításra.';
        } else {
            if ($followUp !== '') {
                $lines[] = $locale === 'en' ? 'Optional focus from the seeker:' : 'Opcionális fókusz a kérdezőtől:';
                $lines[] = $followUp;
                $lines[] = '';
            }
            $lines[] = $locale === 'en'
                ? 'Expand the reading now with deeper practical detail.'
                : 'Most fejtsd ki bővebben, gyakorlati mélységgel.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    private function formatCards(string $locale, array $cards): string
    {
        $roles = $locale === 'en'
            ? ['Your Celestial Master', 'Your Chosen Figure']
            : ['Választott Égi mestered', 'Választott Figurád'];

        $blocks = [];
        foreach ($cards as $index => $card) {
            $polarity = (string) ($card['polarity'] ?? $card['variant'] ?? 'positive');
            $tone = in_array($polarity, ['shadow', 'negative', 'negatív', 'korlátozó'], true)
                ? ($locale === 'en' ? 'restrictive / shadow' : 'korlátozó / árnyék')
                : ($locale === 'en' ? 'supportive / light' : 'támogató / light');
            $role = (string) ($card['slot_title'] ?? ($roles[$index] ?? ('slot '.($index + 1))));

            $blocks[] = implode("\n", [
                sprintf('%d) %s', $index + 1, $role),
                'deck: '.(string) ($card['deck'] ?? ''),
                'figure: '.(string) ($card['figure_name'] ?? $card['figure_id'] ?? '').' ('.(string) ($card['figure_id'] ?? '').')',
                'keyword: '.(string) ($card['keyword'] ?? ''),
                'blurb: '.(string) ($card['blurb'] ?? ''),
                'polarity: '.$polarity.' ('.$tone.')',
                'chart_or_caption: '.(string) ($card['astro_caption'] ?? ''),
            ]);
        }

        return implode("\n\n", $blocks);
    }
}
