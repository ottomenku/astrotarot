<?php

namespace Tests\Unit;

use App\Services\AstroScoringEngine;
use App\Services\TarotPositionPolarity;
use InvalidArgumentException;
use Tests\TestCase;

class TarotPositionPolarityTest extends TestCase
{
    public function test_empty_records_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(TarotPositionPolarity::class)->assign(['records' => []]);
    }

    public function test_invalid_bias_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TarotPositionPolarity(0.5);
    }

    public function test_assigns_independent_slot_tones_deterministically(): void
    {
        $analysis = app(AstroScoringEngine::class)->analyze($this->sampleChart());
        $positions = app(TarotPositionPolarity::class)->assign($analysis);

        $this->assertCount(3, $positions['positions']);
        foreach ($positions['positions'] as $position) {
            $this->assertContains($position['besorolas'], ['pozitív', 'negatív']);
            $this->assertNotSame(0, $position['ertek']);
            $this->assertArrayHasKey('reszertekek', $position);
        }

        $again = app(TarotPositionPolarity::class)->assign($analysis);
        $this->assertSame($positions, $again);
        $this->assertSame(
            $positions['summary']['keplet_azonosito'],
            $again['summary']['keplet_azonosito']
        );
    }

    public function test_custom_positions_override_defaults(): void
    {
        $analysis = app(AstroScoringEngine::class)->analyze($this->sampleChart());
        $result = app(TarotPositionPolarity::class)->assign($analysis, [
            [
                'key' => 'egyedi',
                'name' => 'Egyedi hely',
                'focus' => ['nap' => 1.0],
            ],
        ]);

        $this->assertCount(1, $result['positions']);
        $this->assertSame('egyedi', $result['positions'][0]['kulcs']);
        $this->assertSame('Egyedi hely', $result['positions'][0]['nev']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleChart(): array
    {
        return [
            ['object' => 'Sun', 'sign' => 'Cancer', 'degree' => 27.0],
            ['object' => 'Moon', 'sign' => 'Aries', 'degree' => 12.3],
            ['object' => 'Mercury', 'sign' => 'Leo', 'degree' => 5.2, 'retrograde' => true],
            ['object' => 'Venus', 'sign' => 'Gemini', 'degree' => 28.0],
            ['object' => 'Mars', 'sign' => 'Sagittarius', 'degree' => 11.4],
            ['object' => 'Jupiter', 'sign' => 'Cancer', 'degree' => 9.8],
            ['object' => 'Saturn', 'sign' => 'Aries', 'degree' => 1.5],
            ['object' => 'ASC', 'sign' => 'Libra', 'degree' => 18.0],
            ['object' => 'MC', 'sign' => 'Cancer', 'degree' => 20.1],
        ];
    }
}
