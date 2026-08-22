<?php

namespace Tests\Unit;

use App\Services\OpenAIClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAIClientTest extends TestCase
{
    public function test_empty_api_key_is_rejected(): void
    {
        config([
            'services.openai.driver' => 'openai',
            'services.openai.api_key' => '   ',
        ]);
        Http::preventStrayRequests();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY is not configured.');

        app(OpenAIClient::class)->chat([
            ['role' => 'user', 'content' => 'OK'],
        ]);
    }

    public function test_key_whitespace_is_trimmed_before_request(): void
    {
        config([
            'services.openai.api_key' => ' test-key ',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'OK']],
                ],
            ], 200),
        ]);

        $response = app(OpenAIClient::class)->chat([
            ['role' => 'user', 'content' => 'OK'],
        ]);

        $this->assertTrue($response->successful());
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    public function test_ollama_does_not_require_an_api_key(): void
    {
        config([
            'services.openai.driver' => 'ollama',
            'services.openai.api_key' => '',
            'services.openai.base_url' => 'http://127.0.0.1:11434/v1',
            'services.openai.model' => 'llama3.2',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '127.0.0.1:11434/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'OK']],
                ],
            ], 200),
        ]);

        $response = app(OpenAIClient::class)->chat([
            ['role' => 'user', 'content' => 'OK'],
        ]);

        $this->assertTrue($response->successful());
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '127.0.0.1:11434/v1/chat/completions')
                && $request['model'] === 'llama3.2'
                && ! $request->hasHeader('Authorization');
        });
    }

    public function test_ollama_falls_back_to_openai_when_unreachable(): void
    {
        config([
            'services.openai.driver' => 'ollama',
            'services.openai.api_key' => 'sk-test',
            'services.openai.base_url' => 'http://127.0.0.1:11434/v1',
            'services.openai.model' => 'llama3.2',
            'services.openai.fallback_to_openai' => true,
            'services.openai.cloud_base_url' => 'https://api.openai.com/v1',
            'services.openai.cloud_model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '11434')) {
                throw new \Illuminate\Http\Client\ConnectionException('Failed to connect');
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'cloud']],
                ],
            ], 200);
        });

        $response = app(OpenAIClient::class)->chat([
            ['role' => 'user', 'content' => 'OK'],
        ]);

        $this->assertTrue($response->successful());
        $this->assertSame('cloud', $response->json('choices.0.message.content'));
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions')
                && $request['model'] === 'gpt-4o-mini';
        });
    }
}
