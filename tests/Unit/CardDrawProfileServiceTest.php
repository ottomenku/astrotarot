<?php

namespace Tests\Unit;

use App\Services\AstroScoringEngine;
use App\Services\CardDrawProfileService;
use App\Services\TarotPositionPolarity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardDrawProfileServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_profile_uses_sun_sign_element_modality_and_tone(): void
    {
        $service = app(CardDrawProfileService::class);

        $profile = $service->fromEphemerisResult([
            'objects' => [
                ['name' => 'Sun', 'sign' => 'Scorpio', 'degree' => 10, 'kind' => 'planet'],
            ],
            'scoring' => [
                'summary_clean' => [
                    'elem_dominancia' => ['nev' => 'víz', 'ertek' => 8],
                    'minoseg_dominancia' => ['nev' => 'szilárd', 'ertek' => 7],
                    'pozitiv_negativ' => ['nev' => 'negatív', 'netto_ertek' => -2],
                ],
                'tarot_helyek' => [
                    'positions' => [
                        ['besorolas' => 'pozitív'],
                        ['besorolas' => 'negatív'],
                        ['besorolas' => 'pozitív'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('scorpio', $profile['sun_sign']);
        $this->assertSame('víz', $profile['element']);
        $this->assertSame('szilárd', $profile['modality']);
        $this->assertSame('negatív', $profile['tone']);
        $this->assertSame(['pozitív', 'negatív', 'pozitív'], $profile['tones']);
        $this->assertSame(['positive', 'shadow', 'positive'], $profile['variants']);
        $this->assertSame(['scorpio', 'water', 'fixed'], $profile['slots']);
        $this->assertSame(['sun_sign', 'element', 'modality'], $profile['slot_kinds']);
        $this->assertSame('scoring', $profile['draw_mode']);
    }

    public function test_slot_allocation_can_reorder_backgrounds(): void
    {
        $settings = app(\App\Services\AppSettings::class);
        $settings->putMany([
            \App\Services\AppSettings::SLOT_ALLOCATION => json_encode([
                'modality',
                'sun_sign',
                'element',
            ]),
        ]);

        $profile = app(CardDrawProfileService::class)->fromEphemerisResult([
            'objects' => [
                ['name' => 'Sun', 'sign' => 'Scorpio', 'degree' => 10, 'kind' => 'planet'],
            ],
            'scoring' => [
                'summary_clean' => [
                    'elem_dominancia' => ['nev' => 'víz', 'ertek' => 8],
                    'minoseg_dominancia' => ['nev' => 'szilárd', 'ertek' => 7],
                    'pozitiv_negativ' => ['nev' => 'pozitív', 'netto_ertek' => 2],
                ],
            ],
        ], 'scoring');

        $this->assertSame(['fixed', 'scorpio', 'water'], $profile['slots']);
        $this->assertSame(['modality', 'sun_sign', 'element'], $profile['slot_kinds']);
        $this->assertSame(['szilárd', 'scorpio', 'víz'], $profile['slot_values']);
    }

    public function test_single_random1_is_not_forced_by_fixed_neighbors(): void
    {
        $settings = app(\App\Services\AppSettings::class);
        $settings->putMany([
            \App\Services\AppSettings::SLOT_ALLOCATION => json_encode([
                'random1',
                'element',
                'modality',
            ]),
        ]);

        $seen = [];
        for ($i = 0; $i < 40; $i++) {
            $profile = app(CardDrawProfileService::class)->fromEphemerisResult([
                'objects' => [
                    ['name' => 'Sun', 'sign' => 'Leo', 'degree' => 1, 'kind' => 'planet'],
                ],
                'scoring' => [
                    'summary_clean' => [
                        'elem_dominancia' => ['nev' => 'tűz', 'ertek' => 5],
                        'minoseg_dominancia' => ['nev' => 'fő', 'ertek' => 4],
                        'pozitiv_negativ' => ['nev' => 'pozitív', 'netto_ertek' => 1],
                    ],
                ],
            ], 'scoring');

            $seen[$profile['slot_kinds'][0]] = true;
            $this->assertSame('element', $profile['slot_kinds'][1]);
            $this->assertSame('modality', $profile['slot_kinds'][2]);
        }

        $this->assertGreaterThan(
            1,
            count($seen),
            'random1 should sometimes pick kinds other than the leftover sun_sign'
        );
    }

    public function test_three_random1_slots_are_a_permutation(): void
    {
        $settings = app(\App\Services\AppSettings::class);
        $settings->putMany([
            \App\Services\AppSettings::SLOT_ALLOCATION => json_encode([
                'random1',
                'random1',
                'random1',
            ]),
        ]);

        $profile = app(CardDrawProfileService::class)->fromEphemerisResult([
            'objects' => [
                ['name' => 'Sun', 'sign' => 'Leo', 'degree' => 1, 'kind' => 'planet'],
            ],
            'scoring' => [
                'summary_clean' => [
                    'elem_dominancia' => ['nev' => 'tűz', 'ertek' => 5],
                    'minoseg_dominancia' => ['nev' => 'fő', 'ertek' => 4],
                    'pozitiv_negativ' => ['nev' => 'pozitív', 'netto_ertek' => 1],
                ],
            ],
        ], 'scoring');

        $kinds = $profile['slot_kinds'];
        sort($kinds);
        $this->assertSame(['element', 'modality', 'sun_sign'], $kinds);
    }

    public function test_random_polarity_keeps_scored_backgrounds(): void
    {
        $profile = app(CardDrawProfileService::class)->fromEphemerisResult([
            'objects' => [
                ['name' => 'Sun', 'sign' => 'Scorpio', 'degree' => 10, 'kind' => 'planet'],
            ],
            'scoring' => [
                'summary_clean' => [
                    'elem_dominancia' => ['nev' => 'víz', 'ertek' => 8],
                    'minoseg_dominancia' => ['nev' => 'szilárd', 'ertek' => 7],
                    'pozitiv_negativ' => ['nev' => 'negatív', 'netto_ertek' => -2],
                ],
                'tarot_helyek' => [
                    'positions' => [
                        ['besorolas' => 'pozitív'],
                        ['besorolas' => 'negatív'],
                        ['besorolas' => 'pozitív'],
                    ],
                ],
            ],
        ], 'random_polarity');

        $this->assertSame(['scorpio', 'water', 'fixed'], $profile['slots']);
        $this->assertSame('random_polarity', $profile['draw_mode']);
        $this->assertCount(3, $profile['variants']);
        foreach ($profile['variants'] as $variant) {
            $this->assertContains($variant, ['positive', 'shadow']);
        }
    }

    public function test_positive_tone_uses_positive_variant(): void
    {
        $profile = app(CardDrawProfileService::class)->fromEphemerisResult([
            'objects' => [
                ['name' => 'Sun', 'sign' => 'Leo', 'degree' => 1, 'kind' => 'planet'],
            ],
            'scoring' => [
                'summary_clean' => [
                    'elem_dominancia' => ['nev' => 'tűz', 'ertek' => 5],
                    'minoseg_dominancia' => ['nev' => 'változó', 'ertek' => 4],
                    'pozitiv_negativ' => ['nev' => 'pozitív', 'netto_ertek' => 3],
                ],
            ],
        ]);

        $this->assertSame('positive', $profile['variant']);
        $this->assertSame(['positive', 'positive', 'positive'], $profile['variants']);
        $this->assertSame(['leo', 'fire', 'mutable'], $profile['slots']);
    }

    public function test_air_and_cardinal_mapping(): void
    {
        $profile = app(CardDrawProfileService::class)->fromEphemerisResult([
            'objects' => [
                ['name' => 'Sun', 'sign' => 'Libra', 'degree' => 4, 'kind' => 'planet'],
            ],
            'scoring' => [
                'summary_clean' => [
                    'elem_dominancia' => ['nev' => 'levegő', 'ertek' => 6],
                    'minoseg_dominancia' => ['nev' => 'fő', 'ertek' => 5],
                    'pozitiv_negativ' => ['nev' => 'pozitív', 'netto_ertek' => 1],
                ],
            ],
        ]);

        $this->assertSame(['libra', 'air', 'cardinal'], $profile['slots']);
        $this->assertSame('positive', $profile['variant']);
    }

    public function test_tarot_position_polarity_assigns_independent_slot_tones(): void
    {
        $analysis = app(AstroScoringEngine::class)->analyze([
            ['object' => 'Sun', 'sign' => 'Cancer', 'degree' => 27.0],
            ['object' => 'Moon', 'sign' => 'Aries', 'degree' => 12.3],
            ['object' => 'Mercury', 'sign' => 'Leo', 'degree' => 5.2, 'retrograde' => true],
            ['object' => 'Venus', 'sign' => 'Gemini', 'degree' => 28.0],
            ['object' => 'Mars', 'sign' => 'Sagittarius', 'degree' => 11.4],
            ['object' => 'Jupiter', 'sign' => 'Cancer', 'degree' => 9.8],
            ['object' => 'Saturn', 'sign' => 'Aries', 'degree' => 1.5],
            ['object' => 'ASC', 'sign' => 'Libra', 'degree' => 18.0],
            ['object' => 'MC', 'sign' => 'Cancer', 'degree' => 20.1],
        ]);

        $positions = app(TarotPositionPolarity::class)->assign($analysis);

        $this->assertCount(3, $positions['positions']);
        foreach ($positions['positions'] as $position) {
            $this->assertContains($position['besorolas'], ['pozitív', 'negatív']);
            $this->assertNotSame(0, $position['ertek']);
        }

        $again = app(TarotPositionPolarity::class)->assign($analysis);
        $this->assertSame($positions, $again);
    }
}
