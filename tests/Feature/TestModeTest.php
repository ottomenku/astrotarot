<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_site_shows_maintenance_to_guests(): void
    {
        app(AppSettings::class)->putMany([
            AppSettings::SITE_ENABLED => '0',
        ]);

        $this->get(route('home'))
            ->assertStatus(503)
            ->assertSee(__('messages.maintenance_title'), false);
    }

    public function test_closed_site_still_allows_login_and_admin(): void
    {
        app(AppSettings::class)->putMany([
            AppSettings::SITE_ENABLED => '0',
        ]);

        $this->get(route('login'))->assertOk();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.test-mode.edit'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk();
    }

    public function test_non_admin_cannot_open_home_when_site_closed(): void
    {
        app(AppSettings::class)->putMany([
            AppSettings::SITE_ENABLED => '0',
        ]);

        $user = User::factory()->role(Role::Pro)->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertStatus(503);
    }

    public function test_llm_disabled_blocks_reading_endpoint(): void
    {
        app(AppSettings::class)->putMany([
            AppSettings::LLM_ENABLED => '0',
        ]);

        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)->postJson(route('reading'), [
            'question' => 'Mit tegyek most a kapcsolatommal?',
            'cards' => [
                [
                    'figure_id' => '01-the-magician',
                    'background_id' => 'leo-positive',
                ],
                [
                    'figure_id' => '02-the-high-priestess',
                    'background_id' => 'fire-shadow',
                ],
            ],
        ])
            ->assertStatus(503)
            ->assertJson([
                'error' => __('messages.llm_disabled'),
            ]);
    }

    public function test_admin_can_update_test_mode_flags(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.test-mode.update'), [
                // site_enabled unchecked
                'llm_enabled' => '1',
                'llm_route' => AppSettings::LLM_ROUTE_OPENAI,
            ])
            ->assertRedirect(route('admin.test-mode.edit'));

        $settings = app(AppSettings::class);
        $this->assertFalse($settings->siteEnabled());
        $this->assertTrue($settings->llmEnabled());
        $this->assertSame(AppSettings::LLM_ROUTE_OPENAI, $settings->llmRoute());
    }

    public function test_admin_can_choose_ollama_models(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.test-mode.edit'))
            ->assertOk()
            ->assertSee(__('admin.test_mode.llm_route'), false)
            ->assertSee('llama3.2', false)
            ->assertSee('qwen2.5:14b', false);

        $this->actingAs($admin)
            ->put(route('admin.test-mode.update'), [
                'site_enabled' => '1',
                'llm_enabled' => '1',
                'llm_route' => AppSettings::LLM_ROUTE_OLLAMA_SMALL,
            ])
            ->assertRedirect(route('admin.test-mode.edit'));

        $this->assertSame(
            AppSettings::LLM_ROUTE_OLLAMA_SMALL,
            app(AppSettings::class)->llmRoute()
        );
    }
}
