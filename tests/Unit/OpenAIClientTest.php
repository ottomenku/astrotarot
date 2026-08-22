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
        config(['services.openai.api_key' => '   ']);
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
}
