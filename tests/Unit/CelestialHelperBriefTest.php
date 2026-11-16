<?php

namespace Tests\Unit;

use App\Support\CelestialHelperBrief;
use Tests\TestCase;

class CelestialHelperBriefTest extends TestCase
{
    public function test_planet_line_carries_position_motion_house_and_aspect_angles(): void
    {
        $lines = (new CelestialHelperBrief)->lines('mars', 'hu', 'shadow', $this->chart());

        $this->assertSame([
            'égi segítő: ♂ Mars (mars), polaritás shadow',
            'leírás: Bátorság, harag, vágás, szexuális erő, verseny, a határ, amelyet meg kell védeni. A program előjele nehezítő, nem azonos a rosszal.',
            'asztro: ♂ Mars; pos: ♌ Oroszlán, R, 11°, V. ház; Rel: ☿ Merkúr 90°, ♀ Vénusz 60°',
        ], $lines);
    }

    public function test_sign_line_lists_planets_inside_it(): void
    {
        $astro = (new CelestialHelperBrief)->astro('cancer', 'hu', [
            'objects' => [
                ['id' => 'jupiter', 'kind' => 'planet', 'sign' => 'cancer', 'degree' => 25, 'house' => 4, 'retrograde' => false],
                ['id' => 'mars', 'kind' => 'planet', 'sign' => 'cancer', 'degree' => 10, 'house' => 4, 'retrograde' => true],
                ['id' => 'venus', 'kind' => 'planet', 'sign' => 'leo', 'degree' => 2, 'house' => 5, 'retrograde' => false],
            ],
            'aspects' => [],
        ]);

        $this->assertSame('♋ Rák: ♂ Mars, R, 10°, ♃ Jupiter, N, 25°', $astro);
    }

    public function test_english_planet_line_uses_english_names(): void
    {
        $astro = (new CelestialHelperBrief)->astro('mars', 'en', $this->chart());

        $this->assertSame('♂ Mars; pos: ♌ Leo, R, 11°, 5th house; Rel: ☿ Mercury 90°, ♀ Venus 60°', $astro);
    }

    public function test_missing_chart_still_names_the_helper(): void
    {
        $astro = (new CelestialHelperBrief)->astro('mars', 'hu', null);

        $this->assertSame('♂ Mars; pos: —; Rel: —', $astro);
    }

    /**
     * @return array{objects: list<array<string, mixed>>, aspects: list<array<string, mixed>>}
     */
    private function chart(): array
    {
        return [
            'objects' => [
                ['id' => 'mercury', 'kind' => 'planet', 'sign' => 'virgo', 'degree' => 4, 'house' => 6, 'retrograde' => false],
                ['id' => 'venus', 'kind' => 'planet', 'sign' => 'leo', 'degree' => 2, 'house' => 5, 'retrograde' => false],
                ['id' => 'mars', 'kind' => 'planet', 'sign' => 'leo', 'degree' => 11, 'house' => 5, 'retrograde' => true],
                ['id' => 'jupiter', 'kind' => 'planet', 'sign' => 'cancer', 'degree' => 25, 'house' => 4, 'retrograde' => false],
            ],
            'aspects' => [
                ['a_id' => 'venus', 'b_id' => 'mars', 'type' => 'szextil'],
                ['a_id' => 'mars', 'b_id' => 'mercury', 'type' => 'kvadrát'],
            ],
        ];
    }
}
