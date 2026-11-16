<?php

namespace App\Services;

use App\Models\Game;
use RuntimeException;

class QuestionClassifier
{
    public function __construct(
        private readonly OpenAIClient $openAI,
        private readonly AppSettings $settings,
    ) {}

    /**
     * @return array{category: string, dimension: string}
     */
    public function classify(string $locale, string $question): array
    {
        $system = $this->settings->classifyPrompt($locale);
        $first = $this->complete([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ]);
        $parsed = $this->parse($first);
        if ($parsed !== null) {
            return $parsed;
        }

        $second = $this->complete([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
            ['role' => 'assistant', 'content' => $first],
            ['role' => 'user', 'content' => $locale === 'en'
                ? 'Reply with only the JSON object.'
                : 'A válasz csak a JSON objektum legyen.'],
        ]);

        return $this->parse($second) ?? [
            'category' => Game::SPREAD_NA,
            'dimension' => Game::DIMENSION_NA,
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function complete(array $messages): string
    {
        $result = $this->openAI->complete($messages, null, ['temperature' => 0.2]);
        if (! $result->response->successful()) {
            throw new RuntimeException('classify_failed');
        }

        return trim((string) data_get($result->response->json(), 'choices.0.message.content', ''));
    }

    /**
     * @return array{category: string, dimension: string}|null
     */
    private function parse(string $raw): ?array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw) ?? $raw;
        if (! preg_match('/\{.*\}/s', $raw, $match)) {
            return null;
        }
        $data = json_decode($match[0], true);
        if (! is_array($data)) {
            return null;
        }

        $category = strtoupper(trim((string) ($data['category'] ?? '')));
        $dimension = strtoupper(trim((string) ($data['dimension'] ?? '')));
        if ($category === 'NA') {
            $category = Game::SPREAD_NA;
        }
        if ($dimension === 'NA') {
            $dimension = Game::DIMENSION_NA;
        }
        if (! in_array($category, Game::SPREAD_TYPES, true) || ! in_array($dimension, Game::DIMENSIONS, true)) {
            return null;
        }

        return [
            'category' => $category,
            'dimension' => $dimension,
        ];
    }
}
