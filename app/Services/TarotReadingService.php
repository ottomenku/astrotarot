<?php

namespace App\Services;

use App\Models\ReadingLog;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TarotReadingService
{
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
        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        if ($question === '' || count($cards) !== 3) {
            throw new RuntimeException('A reading requires a question and exactly 3 cards.');
        }

        $systemPrompt = $this->settings->systemPrompt($locale);
        $userPrompt = $this->userPrompt($locale, $question, $cards, $astro);

        $log = ReadingLog::query()->create([
            'locale' => $locale,
            'question' => $question,
            'cards' => $cards,
            'astro' => $astro,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'response' => null,
            'model' => $model,
            'status' => 'pending',
            'error' => null,
            'ip' => $ip,
        ]);

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], $model, [
                'temperature' => 0.85,
                'max_tokens' => 3500,
            ]);
        } catch (Throwable $e) {
            Log::error('Tarot reading OpenAI request failed', ['error' => $e->getMessage()]);
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

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  array<string, mixed>  $astro
     */
    private function userPrompt(string $locale, string $question, array $cards, array $astro): string
    {
        $roles = $locale === 'en'
            ? ['Current situation', 'Help / Obstacle', 'Do / Don’t']
            : ['Jelen helyzet', 'Segítség / Akadály', 'Tedd / Ne tedd'];

        $lines = [];
        $lines[] = $locale === 'en' ? 'Question:' : 'Kérdés:';
        $lines[] = $question;
        $lines[] = '';
        $lines[] = $locale === 'en' ? 'Astrological context of the draw:' : 'A húzás asztrológiai kontextusa:';
        $lines[] = json_encode($astro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lines[] = '';
        $lines[] = $locale === 'en' ? 'The three cards:' : 'A három lap:';

        foreach ($cards as $index => $card) {
            $polarity = (string) ($card['polarity'] ?? $card['variant'] ?? 'positive');
            $tone = in_array($polarity, ['shadow', 'negative', 'negatív', 'korlátozó'], true)
                ? ($locale === 'en' ? 'restrictive / shadow' : 'korlátozó / árnyék')
                : ($locale === 'en' ? 'supportive / positive' : 'támogató / pozitív');

            $lines[] = sprintf(
                '%d) %s | figura: %s (%s) | kulcsszó: %s | háttér: %s | polaritás: %s (%s) | asztro felirat: %s',
                $index + 1,
                $roles[$index] ?? ('slot '.($index + 1)),
                (string) ($card['figure_name'] ?? $card['figure_id'] ?? ''),
                (string) ($card['figure_id'] ?? ''),
                (string) ($card['keyword'] ?? ''),
                (string) ($card['background_id'] ?? $card['background'] ?? ''),
                $polarity,
                $tone,
                (string) ($card['astro_caption'] ?? '')
            );
        }

        $lines[] = '';
        $lines[] = $locale === 'en'
            ? 'Write the full three-section reading now.'
            : 'Most írd meg a teljes, háromszakaszos értelmezést.';

        return implode("\n", $lines);
    }
}
