<?php

namespace Tests\Unit;

use App\Services\EphemerisService;
use Tests\TestCase;

class EphemerisServiceTest extends TestCase
{
    public function test_python_ephemeris_returns_objects_with_sign_and_degree(): void
    {
        $python = (string) config('ephemeris.python_bin');
        $script = (string) config('ephemeris.script');

        if (! is_file($python) || ! is_file($script)) {
            $this->markTestSkipped('Ephemeris Python environment is not available.');
        }

        $result = app(EphemerisService::class)->calculate([
            'datetime_utc' => '2000-01-01T12:00:00Z',
            'lat' => 47.4979,
            'lon' => 19.0402,
        ]);

        $this->assertArrayHasKey('objects', $result);
        $this->assertNotEmpty($result['objects']);

        $sun = collect($result['objects'])->firstWhere('name', 'Sun');
        $this->assertNotNull($sun);
        $this->assertArrayHasKey('sign', $sun);
        $this->assertArrayHasKey('degree', $sun);
        $this->assertArrayHasKey('fixed_star', $sun);
    }
}
