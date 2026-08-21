<?php

namespace Tests\Unit;

use App\Services\AstroScoringEngine;
use InvalidArgumentException;
use Tests\TestCase;

class AstroScoringEngineTest extends TestCase
{
    public function test_empty_records_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AstroScoringEngine::class)->analyze([]);
    }

    public function test_degree_must_be_inside_sign(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AstroScoringEngine::class)->analyze([
            ['object' => 'Sun', 'sign' => 'Leo', 'degree' => 30],
        ]);
    }

    public function test_sun_in_leo_scores_fire_and_domicile(): void
    {
        $analysis = app(AstroScoringEngine::class)->analyze([
            ['object' => 'Sun', 'sign' => 'Leo', 'degree' => 10],
        ]);

        $sun = $analysis['records'][0];
        $this->assertSame('tűz', $sun['elem']);
        $this->assertSame('szilárd', $sun['minoseg']);
        $this->assertSame('pozitív', $sun['pozitiv_negativ_besorolas']);
        $this->assertStringContainsString('uralmi helyzet', $sun['magyarazat']);
        $this->assertSame('tűz', $analysis['summary_clean']['elem_dominancia']['nev']);
    }

    public function test_hungarian_aliases_are_accepted(): void
    {
        $analysis = app(AstroScoringEngine::class)->analyze([
            ['objektum' => 'Nap', 'jegy' => 'Oroszlán', 'fok' => 12.5, 'retrograd' => false],
            ['objektum' => 'Hold', 'jegy' => 'Rák', 'fok' => 3],
        ]);

        $this->assertSame('Nap', $analysis['records'][0]['normalizalt_objektum']);
        $this->assertSame('Hold', $analysis['records'][1]['normalizalt_objektum']);
        $this->assertSame('tűz', $analysis['records'][0]['elem']);
        $this->assertSame('víz', $analysis['records'][1]['elem']);
    }

    public function test_retrograde_reduces_own_contribution(): void
    {
        $direct = app(AstroScoringEngine::class)->analyze([
            ['object' => 'Mercury', 'sign' => 'Gemini', 'degree' => 8, 'retrograde' => false],
        ]);
        $retro = app(AstroScoringEngine::class)->analyze([
            ['object' => 'Mercury', 'sign' => 'Gemini', 'degree' => 8, 'retrograde' => true],
        ]);

        $this->assertGreaterThan(
            $retro['records'][0]['elem_ertek'],
            $direct['records'][0]['elem_ertek']
        );
        $this->assertStringContainsString('Retrográd', $retro['records'][0]['magyarazat']);
    }

    public function test_opposition_is_detected_as_negative_aspect(): void
    {
        $analysis = app(AstroScoringEngine::class)->analyze([
            ['object' => 'Sun', 'sign' => 'Aries', 'degree' => 10],
            ['object' => 'Moon', 'sign' => 'Libra', 'degree' => 10],
        ]);

        $this->assertNotEmpty($analysis['aspects']);
        $opposition = collect($analysis['aspects'])->firstWhere('fenyszog', 'oppozíció');
        $this->assertNotNull($opposition);
        $this->assertSame('negatív', $opposition['jelleg']);
    }
}
