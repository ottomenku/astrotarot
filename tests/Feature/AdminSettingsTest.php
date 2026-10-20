<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_draw_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'min_question_chars' => 15,
                'site_enabled' => '1',
                'registration_enabled' => '1',
                'llm_enabled' => '1',
                'llm_route' => AppSettings::LLM_ROUTE_OPENAI,
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $settings = app(AppSettings::class);
        $this->assertSame(15, $settings->minQuestionChars());
        $this->assertFalse($settings->paidFeaturesEnabled());
    }

    public function test_admin_can_turn_paid_features_back_on(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'min_question_chars' => 10,
                'site_enabled' => '1',
                'registration_enabled' => '1',
                'llm_enabled' => '1',
                'paid_features_enabled' => '1',
                'llm_route' => AppSettings::LLM_ROUTE_OPENAI,
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertTrue(app(AppSettings::class)->paidFeaturesEnabled());
    }

    public function test_admin_can_update_and_reset_prompts(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = app(AppSettings::class);
        $custom = trim(str_repeat('Egyedi prompt. ', 4));

        $this->actingAs($admin)
            ->put(route('admin.prompts.update'), [
                'system_prompt_hu' => $custom,
                'system_prompt_en' => $custom,
                'base_prompt_hu' => $custom,
                'base_prompt_en' => $custom,
            ])
            ->assertRedirect(route('admin.prompts.edit'));

        $this->assertSame($custom, $settings->systemPrompt('hu'));
        $this->assertSame($custom, $settings->basePrompt('hu'));

        $this->actingAs($admin)
            ->post(route('admin.prompts.reset'))
            ->assertRedirect(route('admin.prompts.edit'));

        $this->assertSame($settings->defaultSystemPromptHu(), $settings->systemPrompt('hu'));
    }

    public function test_admin_can_update_legal_texts(): void
    {
        $admin = User::factory()->admin()->create();
        $custom = trim(str_repeat('Minta jogi szöveg. ', 4));

        $this->actingAs($admin)
            ->put(route('admin.legal.update'), [
                'terms_hu' => $custom,
                'terms_en' => $custom,
                'privacy_hu' => $custom,
                'privacy_en' => $custom,
            ])
            ->assertRedirect(route('admin.legal.edit'));

        $this->assertSame($custom, app(AppSettings::class)->terms('hu'));
        $this->assertSame($custom, app(AppSettings::class)->privacy('en'));
    }

    public function test_guest_cannot_change_admin_settings(): void
    {
        $this->put(route('admin.settings.update'), [
            'min_question_chars' => 12,
        ])->assertRedirect(route('login'));
    }
}
