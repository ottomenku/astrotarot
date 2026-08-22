<?php

namespace Tests\Feature;

use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckReadingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_fails_when_api_key_is_missing(): void
    {
        config(['services.openai.api_key' => '']);

        $this->artisan('reading:check')
            ->expectsOutputToContain('OPENAI_API_KEY missing')
            ->assertFailed();
    }

    public function test_check_fails_when_llm_is_disabled(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        app(AppSettings::class)->putMany([
            AppSettings::LLM_ENABLED => '0',
        ]);

        $this->artisan('reading:check')
            ->expectsOutputToContain('llm_enabled=0')
            ->assertFailed();
    }

    public function test_check_passes_when_config_is_present(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-4o-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        $this->artisan('reading:check')
            ->expectsOutputToContain('llm=1')
            ->expectsOutputToContain('key_set=1')
            ->assertSuccessful();
    }

    public function test_ping_fails_on_openai_error(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'nope']], 401),
        ]);

        $this->artisan('reading:check', ['--ping' => true])
            ->expectsOutputToContain('openai_http=401')
            ->assertFailed();
    }

    public function test_ping_passes_on_model_reply(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'OK']],
                ],
            ], 200),
        ]);

        $this->artisan('reading:check', ['--ping' => true])
            ->expectsOutputToContain('openai_ok=1')
            ->assertSuccessful();
    }
}
