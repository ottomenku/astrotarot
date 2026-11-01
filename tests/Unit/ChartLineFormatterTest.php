<?php

namespace Tests\Unit;

use App\Services\ChartLineFormatter;
use PHPUnit\Framework\TestCase;

class ChartLineFormatterTest extends TestCase
{
    public function test_lines_include_degree_descendant_fortune_and_one_way_aspects(): void
    {
        $houses = [];
        for ($i = 0; $i < 12; $i++) {
            $houses[] = ['house' => $i + 1, 'longitude' => fmod(90 + ($i * 30), 360)];
        }

        $lines = (new ChartLineFormatter)->fromChart([
            'houses' => $houses,
            'objects' => [
                ['name' => 'Sun', 'longitude' => 120.0, 'sign' => 'Leo', 'degree' => 0.0, 'house' => 10, 'retrograde' => false, 'fixed_star' => 'Regulus'],
                ['name' => 'Moon', 'longitude' => 0.0, 'sign' => 'Aries', 'degree' => 0.0, 'house' => 4, 'retrograde' => true],
                ['name' => 'ASC', 'longitude' => 90.0, 'sign' => 'Cancer', 'degree' => 0.0, 'house' => 1],
            ],
        ]);

        $this->assertStringContainsString('Sun,Leo,0.0,10,N', $lines['positions']);
        $this->assertStringContainsString('Moon,Aries,0.0,4,R', $lines['positions']);
        $this->assertStringContainsString('ASC,Cancer,0.0,1', $lines['positions']);
        $this->assertStringContainsString('DESC,Capricorn,0.0,7', $lines['positions']);
        $this->assertStringContainsString('Fortune,Pisces,0.0,9', $lines['positions']);
        $this->assertStringContainsString('Sun: conjunction Regulus', $lines['aspects']);
        $this->assertStringContainsString('Moon: trine Sun', $lines['aspects']);
        $this->assertStringContainsString('ASC: square Moon', $lines['aspects']);
        $this->assertStringNotContainsString('Sun: trine Moon', $lines['aspects']);
    }
}
