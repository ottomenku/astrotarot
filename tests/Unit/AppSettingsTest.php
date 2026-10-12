<?php

namespace Tests\Unit;

use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_min_question_chars_is_clamped(): void
    {
        $settings = app(AppSettings::class);

        $settings->putMany([AppSettings::MIN_QUESTION_CHARS => '0']);
        $this->assertSame(1, $settings->minQuestionChars());

        $settings->putMany([AppSettings::MIN_QUESTION_CHARS => '400']);
        $this->assertSame(280, $settings->minQuestionChars());

        $settings->putMany([AppSettings::MIN_QUESTION_CHARS => '18']);
        $this->assertSame(18, $settings->minQuestionChars());
    }

    public function test_prompt_falls_back_to_default_when_empty(): void
    {
        $settings = app(AppSettings::class);

        $this->assertSame($settings->defaultSystemPromptHu(), $settings->systemPrompt('hu'));
        $this->assertSame($settings->defaultClarifyPromptEn(), $settings->clarifyPrompt('en'));

        $this->assertSame($settings->defaultTermsHu(), $settings->terms('hu'));
        $this->assertSame($settings->defaultPrivacyEn(), $settings->privacy('en'));

        $settings->putMany([AppSettings::SYSTEM_PROMPT_HU => 'Egyedi magyar rendszerprompt szövege.']);
        $this->assertSame('Egyedi magyar rendszerprompt szövege.', $settings->systemPrompt('hu'));
    }

    public function test_boolean_flags_parse_common_truthy_values(): void
    {
        $settings = app(AppSettings::class);

        $settings->putMany([
            AppSettings::SITE_ENABLED => 'off',
            AppSettings::REGISTRATION_ENABLED => '0',
            AppSettings::LLM_ENABLED => 'yes',
        ]);

        $this->assertFalse($settings->siteEnabled());
        $this->assertFalse($settings->registrationEnabled());
        $this->assertTrue($settings->llmEnabled());
    }

    public function test_llm_route_defaults_from_driver_and_rejects_unknown_values(): void
    {
        $settings = app(AppSettings::class);

        config(['services.openai.driver' => 'openai']);
        $this->assertNull($settings->llmRoute());
        $this->assertSame(AppSettings::LLM_ROUTE_OPENAI, $settings->effectiveLlmRoute());

        config([
            'services.openai.driver' => 'ollama',
            'services.openai.model' => 'qwen2.5:14b',
            'services.openai.ollama_small_model' => 'llama3.2',
        ]);
        $this->assertSame(AppSettings::LLM_ROUTE_OLLAMA_LARGE, $settings->effectiveLlmRoute());

        $settings->putMany([AppSettings::LLM_ROUTE => 'not-a-route']);
        $this->assertNull($settings->llmRoute());

        $settings->putMany([AppSettings::LLM_ROUTE => AppSettings::LLM_ROUTE_OLLAMA_SMALL]);
        $this->assertSame(AppSettings::LLM_ROUTE_OLLAMA_SMALL, $settings->effectiveLlmRoute());
    }
}
