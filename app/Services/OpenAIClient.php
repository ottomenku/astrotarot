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
    public function __construct(private readonly AppSettings $settings)
    {
    }

    public function route(): string
    {
        return $this->settings->effectiveLlmRoute();
    }

    public function driver(): string
    {
        return $this->route() === AppSettings::LLM_ROUTE_OPENAI
            ? 'openai'
            : 'ollama';
    }

    public function selectedModel(?string $override = null): string
    {
        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        return match ($this->route()) {
            AppSettings::LLM_ROUTE_OPENAI => $this->openAiModel(),
            AppSettings::LLM_ROUTE_OLLAMA_SMALL => (string) config('services.openai.ollama_small_model', 'llama3.2'),
            AppSettings::LLM_ROUTE_OLLAMA_LARGE => (string) config('services.openai.ollama_large_model', 'qwen2.5:14b'),
            default => (string) config('services.openai.model', 'gpt-4o-mini'),
        };
    }

    public function baseUrl(): string
    {
        return $this->driver() === 'ollama'
            ? $this->ollamaBaseUrl()
            : $this->openAiBaseUrl();
    }

    public function requiresApiKey(): bool
    {
        if ($this->driver() === 'ollama') {
            return false;
        }

        $base = strtolower($this->openAiBaseUrl());

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
        return $this->complete($messages, $model, $options)->response;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function complete(array $messages, ?string $model = null, array $options = []): LlmChatResult
    {
        $model = $this->selectedModel($model);

        if ($this->driver() === 'openai') {
            $response = $this->postChat(
                $this->openAiBaseUrl(),
                $model,
                $messages,
                $options,
                $this->requiresApiKey(),
            );

            return new LlmChatResult($response, 'openai', $model, false);
        }

        try {
            $response = $this->postChat(
                $this->ollamaBaseUrl(),
                $model,
                $messages,
                $options,
                false,
            );

            return new LlmChatResult($response, 'ollama', $model, false);
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
    private function fallbackOrThrow(Throwable $e, array $messages, array $options): LlmChatResult
    {
        if (! $this->canFallbackToOpenAi()) {
            throw $e;
        }

        Log::warning('Ollama unreachable, falling back to OpenAI', ['error' => $e->getMessage()]);

        $model = $this->openAiModel();
        $response = $this->postChat($this->openAiBaseUrl(), $model, $messages, $options, true);

        return new LlmChatResult($response, 'openai', $model, true);
    }

    private function ollamaBaseUrl(): string
    {
        $base = rtrim((string) config('services.openai.base_url', ''), '/');
        if (str_contains($base, ':11434')) {
            return $base;
        }

        return rtrim((string) config('services.openai.ollama_base_url', 'http://127.0.0.1:11434/v1'), '/');
    }

    private function openAiBaseUrl(): string
    {
        $base = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        if (str_contains($base, ':11434')) {
            return rtrim((string) config('services.openai.cloud_base_url', 'https://api.openai.com/v1'), '/');
        }

        return $base !== '' ? $base : 'https://api.openai.com/v1';
    }

    private function openAiModel(): string
    {
        $driver = strtolower((string) config('services.openai.driver', 'openai'));
        $configured = $driver === 'ollama'
            ? (string) config('services.openai.cloud_model', 'gpt-4o-mini')
            : (string) config('services.openai.model', 'gpt-4o-mini');

        return $configured !== '' ? $configured : 'gpt-4o-mini';
    }
}
