<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Services\CardDrawProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingFrontendContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_exposes_same_origin_reading_contract(): void
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

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="csrf-token"', false)
            ->assertSee('data-game-description', false)
            ->assertSee(__('messages.spread_current'), false)
            ->assertSee(__('messages.spread_change'), false)
            ->assertSee('data-question-wrap', false)
            ->assertSee('data-pack-base=', false)
            ->assertSee('data-game=', false)
            ->assertSee('card-pack', false)
            ->assertSee('data-feature-access=', false)
            ->assertSee('data-llm-enabled="1"', false)
            ->assertSee('data-reset', false)
            ->assertSee('data-reading-followup-send', false)
            ->assertSee('data-reading-followup-expand', false);
    }

    public function test_reading_javascript_maps_backend_errors(): void
    {
        $js = (string) file_get_contents(public_path('templates/art-deco/app.js'));

        $this->assertStringContainsString('function featureState(', $js);
        $this->assertStringContainsString('data-game-description', $js);
        $this->assertStringContainsString('function gameDescription(', $js);
        $this->assertStringContainsString('packBase', $js);
        $this->assertStringContainsString('gameSlots', $js);
        $this->assertStringContainsString('scoring', $js);
        $this->assertStringContainsString('row:', $js);
        $this->assertStringContainsString('function packHasPolarFigures(', $js);
        $this->assertStringContainsString('polarFigures', $js);
        $this->assertStringContainsString('card_back', $js);
        $this->assertStringContainsString('reading.initial', $js);
        $this->assertStringContainsString('sessionExpired', $js);
        $this->assertStringContainsString('sourcePrefix', $js);
        $this->assertStringContainsString("credentials: 'same-origin'", $js);
        $this->assertStringContainsString('response.status === 419', $js);
        $this->assertStringContainsString("querySelectorAll('[data-reset]')", $js);
    }
}
