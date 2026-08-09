<?php

namespace Tests\Feature;

use App\Models\Template;
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

    public function test_home_renders_active_art_deco_template(): void
    {
        Template::factory()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('AstroTarot', false)
            ->assertSee('data-deck-slider', false)
            ->assertSee(asset('templates/art-deco/app.js'), false);
    }

    public function test_locale_can_be_switched(): void
    {
        $this->from(route('home'))
            ->get(route('locale.switch', 'en'))
            ->assertRedirect(route('home'));

        $this->assertSame('en', session('locale'));
    }
}
