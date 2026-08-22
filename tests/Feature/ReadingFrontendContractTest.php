<?php

namespace Tests\Feature;

use App\Models\Template;
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

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="csrf-token"', false)
            ->assertSee('data-reading-url="/reading"', false)
            ->assertSee('data-llm-enabled="1"', false);
    }

    public function test_reading_javascript_maps_backend_errors(): void
    {
        $js = (string) file_get_contents(public_path('templates/art-deco/app.js'));

        $this->assertStringContainsString('function jsonErrorMessage(', $js);
        $this->assertStringContainsString('sessionExpired', $js);
        $this->assertStringContainsString('sourcePrefix', $js);
        $this->assertStringContainsString("credentials: 'same-origin'", $js);
        $this->assertStringContainsString('response.status === 419', $js);
    }
}
