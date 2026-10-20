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
        $this->assertDatabaseCount('user_spread_preferences', 0);
    }

    public function test_saved_spread_belongs_only_to_that_user(): void
    {
        Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        \App\Models\Game::query()->update(['is_default' => false]);
        $default = \App\Models\Game::factory()->default()->create([
            'name_hu' => 'Alap kirakás',
            'name_en' => 'Default spread',
        ]);
        $default->rules()->create([
            'pack_slug' => 'tarot',
            'pack_type' => 'tarot',
            'title_hu' => 'Alap',
            'title_en' => 'Default',
            'info_mode' => 'meaning',
            'row' => 1,
            'place' => 1,
            'draw_order' => 1,
        ]);

        $own = \App\Models\Game::factory()->create([
            'name_hu' => 'Saját kirakás',
            'name_en' => 'Own spread',
        ]);
        $tarot = \App\Models\CardPack::query()->where('slug', 'tarot')->firstOrFail();
        $own->rules()->create([
            'pack_slug' => $tarot->slug,
            'pack_type' => 'tarot',
            'default_pack_id' => $tarot->id,
            'title_hu' => 'Saját',
            'title_en' => 'Own',
            'info_mode' => 'meaning',
            'row' => 1,
            'place' => 1,
            'draw_order' => 1,
        ]);

        $user = \App\Models\User::factory()->create();
        $other = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('home', [
                'spread' => 1,
                'game' => $own->id,
                'pack_tarot' => 'anime-tarot',
            ]))
            ->assertRedirect(route('home'));

        $saved = \App\Models\UserSpreadPreference::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($saved);
        $this->assertSame($own->id, $saved->game_id);
        $this->assertSame(['tarot' => 'anime-tarot'], $saved->packs);
        $this->assertSame(1, \App\Models\UserSpreadPreference::query()->count());

        $this->flushSession();
        $kept = $this->slotPacks($this->actingAs($user)->get(route('home'))->assertOk()->assertSee('Saját kirakás — 1 lapot kell húzni', false)->getContent());
        $this->assertSame(['anime-tarot'], $kept);

        $this->flushSession();
        $this->actingAs($other)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Alap kirakás — 1 lapot kell húzni', false)
            ->assertDontSee('Saját kirakás — 1 lapot kell húzni', false);
    }

    public function test_spread_keeps_each_slots_own_pack_until_one_is_chosen(): void
    {
        Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $tarot = \App\Models\CardPack::query()->where('slug', 'tarot')->firstOrFail();
        $anime = \App\Models\CardPack::query()->where('slug', 'anime-tarot')->firstOrFail();
        $game = \App\Models\Game::factory()->create([
            'name_hu' => 'Két paklis',
            'name_en' => 'Two packs',
        ]);
        $game->rules()->create([
            'pack_slug' => $tarot->slug,
            'pack_type' => 'tarot',
            'default_pack_id' => $tarot->id,
            'title_hu' => 'Első',
            'title_en' => 'First',
            'info_mode' => 'meaning',
            'row' => 1,
            'place' => 1,
            'draw_order' => 1,
        ]);
        $game->rules()->create([
            'pack_slug' => $anime->slug,
            'pack_type' => 'tarot',
            'default_pack_id' => $anime->id,
            'title_hu' => 'Második',
            'title_en' => 'Second',
            'info_mode' => 'meaning',
            'row' => 1,
            'place' => 2,
            'draw_order' => 2,
        ]);

        $this->get(route('home', [
            'draw' => 1,
            'spread' => 1,
            'game' => $game->id,
        ]))->assertRedirect();

        $kept = $this->slotPacks($this->get(route('home', ['draw' => 1]))->assertOk()->getContent());
        $this->assertSame(['tarot', 'anime-tarot'], $kept);

        $this->get(route('home', [
            'draw' => 1,
            'spread' => 1,
            'game' => $game->id,
            'pack_tarot' => 'tacsko-tarot',
        ]))->assertRedirect();

        $overridden = $this->slotPacks($this->get(route('home', ['draw' => 1]))->assertOk()->getContent());
        $this->assertSame(['tacsko-tarot', 'tacsko-tarot'], $overridden);
    }

    /**
     * @return list<string>
     */
    private function slotPacks(string $html): array
    {
        $this->assertSame(1, preg_match("/data-game='([^']+)'/", $html, $matches));
        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($config);

        return array_column($config['slots'] ?? [], 'pack_slug');
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
