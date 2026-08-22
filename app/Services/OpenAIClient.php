<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIClient
{
    private function http(): PendingRequest
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        return Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.openai.timeout', 120));
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function chat(array $messages, ?string $model = null, array $options = []): Response
    {
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = $model ?: (string) config('services.openai.model', 'gpt-4o-mini');

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        return $this->http()->post($baseUrl.'/chat/completions', $payload);
    }
}
