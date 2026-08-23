<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Services\CardDrawProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_falls_back_to_welcome_without_active_template_view(): void
    {
        Template::factory()->create([
            'slug' => 'missing-view',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('messages.welcome'), false);
    }

    public function test_home_renders_landing_for_guests(): void
    {
        Template::factory()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('AstroTarot', false)
            ->assertSee(__('messages.landing_title'), false)
            ->assertSee('landing-tarot.jpg', false)
            ->assertSee('data-auth-modal', false)
            ->assertSee('data-auth-open="login"', false)
            ->assertDontSee('data-deck-slider', false);
    }

    public function test_authenticated_home_renders_active_art_deco_template(): void
    {
        Template::factory()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->mock(CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->once()->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív', 'negatív', 'pozitív'],
                'variants' => ['positive', 'shadow', 'positive'],
                'slots' => ['leo', 'fire', 'cardinal'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-10T12:00:00Z',
            ]);
        });

        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('AstroTarot', false)
            ->assertSee('data-deck-slider', false)
            ->assertSee('data-draw-profile', false)
            ->assertSee('data-reading-url="/reading"', false)
            ->assertSee(asset('templates/art-deco/app.js'), false);
    }

    public function test_locale_can_be_switched(): void
    {
        $this->from(route('home'))
            ->get(route('locale.switch', 'en'))
            ->assertRedirect(route('home'));

        $this->assertSame('en', session('locale'));
    }

    public function test_invalid_locale_is_not_found(): void
    {
        $this->get(route('locale.switch', 'de'))->assertNotFound();
    }
}
