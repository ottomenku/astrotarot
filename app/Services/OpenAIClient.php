<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OpenAIClient
{
    public function driver(): string
    {
        return strtolower((string) config('services.openai.driver', 'openai')) === 'ollama'
            ? 'ollama'
            : 'openai';
    }

    public function requiresApiKey(): bool
    {
        if ($this->driver() === 'ollama') {
            return false;
        }

        $base = strtolower((string) config('services.openai.base_url', ''));

        return ! str_contains($base, ':11434');
    }

    public function canFallbackToOpenAi(): bool
    {
        if ($this->driver() !== 'ollama') {
            return false;
        }

        if (! filter_var(config('services.openai.fallback_to_openai'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return trim((string) config('services.openai.api_key')) !== '';
    }

    private function http(bool $requireKey): PendingRequest
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($requireKey && $apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.openai.timeout', 120));

        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        return $request;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function chat(array $messages, ?string $model = null, array $options = []): Response
    {
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = $model ?: (string) config('services.openai.model', 'gpt-4o-mini');

        try {
            return $this->postChat($baseUrl, $model, $messages, $options, $this->requiresApiKey());
        } catch (ConnectionException $e) {
            return $this->fallbackOrThrow($e, $messages, $options);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function postChat(
        string $baseUrl,
        string $model,
        array $messages,
        array $options,
        bool $requireKey,
    ): Response {
        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        return $this->http($requireKey)->post($baseUrl.'/chat/completions', $payload);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function fallbackOrThrow(Throwable $e, array $messages, array $options): Response
    {
        if (! $this->canFallbackToOpenAi()) {
            throw $e;
        }

        Log::warning('Ollama unreachable, falling back to OpenAI', ['error' => $e->getMessage()]);

        $baseUrl = rtrim((string) config('services.openai.cloud_base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.cloud_model', 'gpt-4o-mini');

        return $this->postChat($baseUrl, $model, $messages, $options, true);
    }
}
