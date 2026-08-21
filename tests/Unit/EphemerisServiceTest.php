<?php

namespace Tests\Unit;

use App\Services\AstroScoringEngine;
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

        try {
            $result = app(EphemerisService::class)->calculate([
                'datetime_utc' => '2000-01-01T12:00:00Z',
                'lat' => 47.4979,
                'lon' => 19.0402,
            ]);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Ephemeris Python environment is not available: '.$e->getMessage());
        }

        $this->assertArrayHasKey('objects', $result);
        $this->assertNotEmpty($result['objects']);

        $sun = collect($result['objects'])->firstWhere('name', 'Sun');
        $this->assertNotNull($sun);
        $this->assertArrayHasKey('sign', $sun);
        $this->assertArrayHasKey('degree', $sun);
        $this->assertArrayHasKey('fixed_star', $sun);
        $this->assertArrayHasKey('elem', $sun);
        $this->assertArrayHasKey('minoseg', $sun);
        $this->assertArrayHasKey('pozitiv_negativ_besorolas', $sun);
        $this->assertArrayHasKey('scoring', $result);
        $this->assertIsArray($result['scoring']['summary'] ?? null);
    }

    public function test_enrich_with_scoring_skips_fixed_stars_and_adds_summary(): void
    {
        $service = app(EphemerisService::class);

        $result = $service->enrichWithScoring([
            'objects' => [
                [
                    'name' => 'Sun',
                    'kind' => 'planet',
                    'sign' => 'Leo',
                    'degree' => 17.5,
                    'fixed_star' => null,
                ],
                [
                    'name' => 'Regulus',
                    'kind' => 'fixed_star',
                    'sign' => 'Leo',
                    'degree' => 0.1,
                    'fixed_star' => null,
                ],
                [
                    'name' => 'True Node',
                    'kind' => 'planet',
                    'sign' => 'Cancer',
                    'degree' => 12.0,
                    'fixed_star' => null,
                ],
            ],
        ]);

        $this->assertSame('tűz', $result['objects'][0]['elem']);
        $this->assertArrayNotHasKey('elem', $result['objects'][1]);
        $this->assertSame('víz', $result['objects'][2]['elem']);
        $this->assertArrayHasKey('elem_dominancia', $result['scoring']['summary']);
        $this->assertArrayHasKey('pozitiv_negativ', $result['scoring']['summary']);
        $this->assertArrayHasKey('summary_clean', $result['scoring']);
        $this->assertArrayHasKey('tarot_helyek', $result['scoring']);
        $this->assertCount(3, $result['scoring']['tarot_helyek']['positions'] ?? []);
        $this->assertDoesNotMatchRegularExpression(
            '/kiegyensúlyozott|semleges/',
            (string) ($result['scoring']['summary_clean']['elem_dominancia']['nev'] ?? '')
        );
        $this->assertContains(
            $result['scoring']['summary_clean']['pozitiv_negativ']['nev'],
            ['pozitív', 'negatív']
        );
    }

    public function test_scoring_engine_analyzes_english_aliases(): void
    {
        $analysis = app(AstroScoringEngine::class)->analyze([
            ['object' => 'Sun', 'sign' => 'Leo', 'degree' => 10],
            ['object' => 'Moon', 'sign' => 'Cancer', 'degree' => 5],
        ]);

        $this->assertCount(2, $analysis['records']);
        $this->assertSame('tűz', $analysis['records'][0]['elem']);
        $this->assertSame('víz', $analysis['records'][1]['elem']);
        $this->assertArrayHasKey('pozitiv_negativ', $analysis['summary']);
        $this->assertArrayHasKey('summary_clean', $analysis);
    }

    public function test_clean_summary_breaks_ties_by_priority(): void
    {
        $engine = app(AstroScoringEngine::class);

        $cleanDominance = new \ReflectionMethod(AstroScoringEngine::class, 'cleanDominance');
        $cleanDominance->setAccessible(true);
        $cleanTone = new \ReflectionMethod(AstroScoringEngine::class, 'cleanToneDominance');
        $cleanTone->setAccessible(true);

        $element = $cleanDominance->invoke(
            $engine,
            ['tűz' => 5.0, 'víz' => 5.1, 'föld' => 5.05, 'levegő' => 1.0],
            ['tűz' => 1, 'víz' => 2, 'föld' => 3, 'levegő' => 4],
        );
        $this->assertSame('tűz', $element['nev']);

        $modality = $cleanDominance->invoke(
            $engine,
            ['fő' => 3.0, 'szilárd' => 3.1, 'változó' => 3.05],
            ['fő' => 1, 'szilárd' => 2, 'változó' => 3],
        );
        $this->assertSame('fő', $modality['nev']);

        $tone = $cleanTone->invoke($engine, 4.0, 4.0);
        $this->assertSame('pozitív', $tone['nev']);
    }
}
