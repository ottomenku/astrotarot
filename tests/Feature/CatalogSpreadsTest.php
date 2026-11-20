<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSpreadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_spreads_are_stored_in_both_languages(): void
    {
        /** @var list<array<string, mixed>> $spreads */
        $spreads = require database_path('data/catalog_spreads.php');

        $this->assertCount(16, $spreads);
        $this->assertSame(1, Game::query()->where('is_default', true)->count());

        $default = Game::query()->where('slug', 'pillanatnyi-iranytu')->firstOrFail();
        $this->assertTrue($default->is_default);
        $this->assertSame('Moment Compass', $default->name_en);
        $this->assertSame(['N/A'], $default->spread_types);
        $this->assertSame(['now'], $default->astro_data_modes);
        $this->assertNull($default->icon);

        $step = $default->rules()->where('draw_order', 3)->firstOrFail();
        $this->assertSame('Next step', $step->title_en);
        $this->assertSame('oracle', $step->pack_type);
        $this->assertSame('none', $step->light_shadow);

        $green = Game::query()->where('slug', 'uzleti-es-jogi-zold-lampa')->firstOrFail();
        $this->assertSame('Business and Legal Green Light', $green->name_en);
        $this->assertSame(['BINARY'], $green->spread_types);
        $this->assertSame(['CAR', 'FIN', 'LEG'], $green->spread_dimensions);
        $this->assertSame('jupiter', $green->rules()->where('draw_order', 1)->value('celestial_helper'));

        $partnership = Game::query()->where('slug', 'parkapcsolati-dinamika')->firstOrFail();
        $this->assertSame('Partnership Dynamic', $partnership->name_en);
        $this->assertCount(5, $partnership->rules);
        $this->assertSame(['now', 'personal', 'relations'], $partnership->astro_data_modes);
        $this->assertStringContainsString('shared future', $partnership->prompt_en);

        $balance = Game::query()->where('slug', 'test-lelek-egyensulytukor')->firstOrFail();
        $this->assertStringContainsString('Do not give a medical diagnosis', $balance->prompt_en);

        $this->assertFalse((bool) Game::query()->where('slug', 'astro-tarot')->value('is_default'));
    }
};
