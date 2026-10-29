<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ReadingSkyLookup;
use Tests\TestCase;

class ReadingSkyLookupTest extends TestCase
{
    public function test_chart_presentation_keeps_only_the_requested_parts(): void
    {
        $lookup = app(ReadingSkyLookup::class);
        $chart = [
            'houses' => [
                ['house' => 1, 'sign' => 'Aries', 'degree' => 10],
                ['house' => 8, 'sign' => 'Scorpio', 'degree' => 4],
            ],
            'objects' => [
                [
                    'name' => 'Mars',
                    'kind' => 'planet',
                    'sign' => 'Scorpio',
                    'degree' => 12,
                    'longitude' => 222,
                    'house' => 8,
                    'retrograde' => false,
                    'fixed_star' => null,
                ],
                [
                    'name' => 'Saturn',
                    'kind' => 'planet',
                    'sign' => 'Leo',
                    'degree' => 15,
                    'longitude' => 135,
                    'house' => 5,
                    'retrograde' => true,
                    'fixed_star' => null,
                ],
                [
                    'name' => 'Antares',
                    'kind' => 'fixed_star',
                    'sign' => 'Sagittarius',
                    'degree' => 9,
                    'longitude' => 249,
                    'house' => 8,
                ],
            ],
        ];

        $planets = $lookup->present($chart, ['planets']);
        $this->assertArrayHasKey('planets', $planets);
        $this->assertArrayNotHasKey('fixed_stars', $planets);
        $this->assertSame('Mars', $planets['planets'][0]['name']);

        $houses = $lookup->present($chart, ['houses']);
        $eighth = collect($houses['houses'])->firstWhere('house', 8);
        $this->assertNotNull($eighth);
        $this->assertContains('Mars', $eighth['occupants']);
        $this->assertContains('Antares', $eighth['occupants']);
        $this->assertNotEmpty($eighth['aspects']);
    }

    public function test_birth_and_residence_coordinates_override_the_site_default(): void
    {
        config(['ephemeris.default_lat' => 1.0, 'ephemeris.default_lon' => 2.0]);
        $lookup = app(ReadingSkyLookup::class);
        $user = new User([
            'birth_latitude' => 47.5,
            'birth_longitude' => 19.0,
            'residence_latitude' => 48.2,
            'residence_longitude' => 16.3,
        ]);

        $birth = $lookup->chartCoordinates('birth', $user);
        $this->assertSame('birth_place', $birth['location']);
        $this->assertSame(47.5, $birth['lat']);
        $this->assertSame(19.0, $birth['lon']);

        $now = $lookup->chartCoordinates('now', $user);
        $this->assertSame('residence', $now['location']);
        $this->assertSame(16.3, $now['lon']);

        $fallback = $lookup->chartCoordinates('now', new User());
        $this->assertSame('default_site_coordinates', $fallback['location']);
        $this->assertSame(1.0, $fallback['lat']);
    }
}
