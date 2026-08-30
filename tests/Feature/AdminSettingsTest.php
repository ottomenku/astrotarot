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
                'draw_mode' => AppSettings::DRAW_MODE_RANDOM_POLARITY,
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $settings = app(AppSettings::class);
        $this->assertSame(15, $settings->minQuestionChars());
        $this->assertSame(AppSettings::DRAW_MODE_RANDOM_POLARITY, $settings->drawMode());
    }

    public function test_admin_can_update_slot_allocation(): void
    {
        $admin = User::factory()->admin()->create();
        $allocation = [
            AppSettings::SLOT_SOURCE_ELEMENT,
            AppSettings::SLOT_SOURCE_RANDOM1,
            AppSettings::SLOT_SOURCE_SUN,
        ];

        $this->actingAs($admin)
            ->put(route('admin.slots.update'), ['slots' => $allocation])
            ->assertRedirect(route('admin.slots.edit'));

        $this->assertSame($allocation, app(AppSettings::class)->slotAllocation());
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
                'clarify_prompt_hu' => $custom,
                'clarify_prompt_en' => $custom,
                'expand_prompt_hu' => $custom,
                'expand_prompt_en' => $custom,
            ])
            ->assertRedirect(route('admin.prompts.edit'));

        $this->assertSame($custom, $settings->systemPrompt('hu'));

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
            'draw_mode' => AppSettings::DRAW_MODE_SCORING,
        ])->assertRedirect(route('login'));
    }
}
