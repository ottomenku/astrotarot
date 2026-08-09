<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EphemerisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EphemerisTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ephemeris_page_renders_table_from_service(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(EphemerisService::class, function ($mock) {
            $mock->shouldReceive('calculate')->once()->andReturn([
                'datetime_utc' => '2026-08-09T12:00:00+00:00',
                'objects' => [
                    [
                        'name' => 'Sun',
                        'kind' => 'planet',
                        'longitude' => 137.5,
                        'sign' => 'Leo',
                        'degree' => 17.5,
                        'fixed_star' => null,
                    ],
                    [
                        'name' => 'Moon',
                        'kind' => 'planet',
                        'longitude' => 150.1,
                        'sign' => 'Virgo',
                        'degree' => 0.1,
                        'fixed_star' => 'Regulus',
                    ],
                ],
            ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.ephemeris.index', [
                'datetime_utc' => '2026-08-09T12:00:00Z',
                'lat' => 47.5,
                'lon' => 19.0,
            ]))
            ->assertOk()
            ->assertSee('Sun', false)
            ->assertSee('Leo', false)
            ->assertSee('Regulus', false);
    }
}
