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
            ->assertDontSee('data-deck-slider', false)
            ->assertSee(__('messages.landing_cta_try'), false)
            ->assertSee('href="'.route('home', ['draw' => 1]).'"', false)
            ->assertDontSee('href="'.route('blog.index').'" class="landing-login-link"', false);
    }

    public function test_guest_can_open_the_draw_from_the_landing(): void
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
                'tones' => ['pozitív', 'pozitív'],
                'variants' => ['positive', 'positive'],
                'slots' => ['leo', 'fire'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-10T12:00:00Z',
            ]);
        });

        $this->get(route('home', ['draw' => 1]))
            ->assertOk()
            ->assertSee('data-deck-slider', false)
            ->assertSee(__('messages.spread_current'), false)
            ->assertSee(__('messages.spread_change'), false)
            ->assertDontSee(__('messages.landing_title'), false);
    }

    public function test_guest_can_switch_the_selected_spread(): void
    {
        Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $game = \App\Models\Game::factory()->create([
            'name_hu' => 'Egylapos próba',
            'name_en' => 'One card trial',
        ]);
        $game->rules()->create([
            'pack_slug' => 'tarot',
            'pack_type' => 'tarot',
            'title_hu' => 'Lap',
            'title_en' => 'Card',
            'info_mode' => 'meaning',
            'row' => 1,
            'place' => 1,
        ]);

        $this->get(route('home', [
            'draw' => 1,
            'spread' => 1,
            'game' => $game->id,
            'pack_tarot' => 'tarot',
        ]))->assertRedirect(route('home', ['draw' => 1]));

        $this->get(route('home', ['draw' => 1]))
            ->assertOk()
            ->assertSee('Egylapos próba — 1 lapot kell húzni', false)
            ->assertSee('name="game"', false);
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
