<?php

namespace App\Services;

use App\Models\ReadingLog;

/**
 * Approximate OpenAI cost of one saved reading.
 * Tokens are counted from the stored text (about 3 characters = 1 token).
 * Prices are USD per token, list price as of 2026-11-01. The forint rate is
 * the market USD/HUF close on that day, rounded.
 */
final class ReadingCostEstimate
{
    public const USD_HUF = 328;

    /**
     * USD per token. Longer names are matched first.
     *
     * @var array<string, array{input: float, output: float}>
     */
    private const PRICES = [
        'gpt-5.4-mini' => ['input' => 0.75 / 1_000_000, 'output' => 4.50 / 1_000_000],
        'gpt-5.4-nano' => ['input' => 0.20 / 1_000_000, 'output' => 1.25 / 1_000_000],
        'gpt-5.4' => ['input' => 2.50 / 1_000_000, 'output' => 15.00 / 1_000_000],
        'gpt-5-mini' => ['input' => 0.25 / 1_000_000, 'output' => 2.00 / 1_000_000],
        'gpt-5-nano' => ['input' => 0.05 / 1_000_000, 'output' => 0.40 / 1_000_000],
        'gpt-4.1-mini' => ['input' => 0.40 / 1_000_000, 'output' => 1.60 / 1_000_000],
        'gpt-4.1' => ['input' => 2.00 / 1_000_000, 'output' => 8.00 / 1_000_000],
        'gpt-4o-mini' => ['input' => 0.15 / 1_000_000, 'output' => 0.60 / 1_000_000],
        'gpt-4o' => ['input' => 2.50 / 1_000_000, 'output' => 10.00 / 1_000_000],
    ];

    /**
     * @return array{
     *     model: string,
     *     input_tokens: int,
     *     output_tokens: int,
     *     total_tokens: int,
     *     huf: float|null,
     *     huf_label: string,
     *     local: bool
     * }
     */
    public function forLog(ReadingLog $log): array
    {
        $model = trim((string) $log->model);
        $input = $this->tokens((string) $log->system_prompt) + $this->tokens((string) $log->user_prompt);
        $output = $this->tokens((string) $log->response);
        $local = (string) $log->llm_provider === 'ollama';
        $price = $local ? null : $this->priceFor($model);
        $huf = null;
        if ($price !== null) {
            $usd = ($input * $price['input']) + ($output * $price['output']);
            $huf = $usd * self::USD_HUF;
        }

        return [
            'model' => $model,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $input + $output,
            'huf' => $huf,
            'huf_label' => $this->hufLabel($huf, $local, $price !== null),
            'local' => $local,
        ];
    }

    private function tokens(string $text): int
    {
        $length = mb_strlen($text);
        if ($length === 0) {
            return 0;
        }

        return (int) max(1, (int) ceil($length / 3));
    }

    /**
     * @return array{input: float, output: float}|null
     */
    private function priceFor(string $model): ?array
    {
        $model = strtolower(trim($model));
        $keys = array_keys(self::PRICES);
        usort($keys, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($keys as $key) {
            if ($model === $key || str_starts_with($model, $key.'-') || str_starts_with($model, $key.':')) {
                return self::PRICES[$key];
            }
        }

        return null;
    }

    private function hufLabel(?float $huf, bool $local, bool $priced): string
    {
        if ($local) {
            return '';
        }
        if (! $priced || $huf === null) {
            return '';
        }
        if ($huf < 0.01) {
            return '< 0,01 Ft';
        }
        $decimals = $huf < 10 ? 2 : 0;

        return number_format($huf, $decimals, ',', ' ').' Ft';
    }
}
