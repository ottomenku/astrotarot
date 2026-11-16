<?php

namespace Tests\Unit;

use App\Services\SkyBrief;
use Tests\TestCase;

class SkyBriefTest extends TestCase
{
    public function test_full_chart_lists_only_later_bodies_and_a_sign_lists_its_occupants(): void
    {
        $brief = new SkyBrief;
        $chart = [
            'objects' => [
                [
                    'name' => 'Sun',
                    'sign' => 'Gemini',
                    'degree' => 11,
                    'house' => 10,
                    'longitude' => 71,
                    'retrograde' => true,
                ],
                [
                    'name' => 'Moon',
                    'sign' => 'Aries',
                    'degree' => 12,
                    'house' => 5,
                    'longitude' => 12,
                    'retrograde' => false,
                ],
                [
                    'name' => 'Mars',
                    'sign' => 'Leo',
                    'degree' => 11,
                    'house' => 5,
                    'longitude' => 131,
                    'retrograde' => true,
                ],
            ],
        ];

        $this->assertSame(
            'Nap: Ikrek 11fok, 10.ház, R. Hold: Kos 12fok, 5.ház, N. Mars: Oroszlán 11fok, 5.ház, R',
            $brief->positions($chart, 'hu'),
        );
        $this->assertSame(
            "Nap: 60fok Hold; 60fok Mars\nHold: 120fok Mars",
            $brief->relations($chart, 'hu'),
        );
        $this->assertSame('Mars, R, 11fok', $brief->signContent($chart, 'Leo', 'hu'));
        $this->assertSame('—', $brief->signContent($chart, 'Cancer', 'hu'));

        $slice = $brief->bodySlice($chart, 'Mars', 'hu');
        $this->assertSame('Oroszlán 11fok, 5.ház, R', $slice['position']);
        $this->assertSame('60fok Nap; 120fok Hold', $slice['relations']);
    }
}
