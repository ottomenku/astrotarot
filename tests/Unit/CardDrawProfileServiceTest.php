<?php

namespace Tests\Unit;

use App\Services\CardDrawProfileService;
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
        $this->assertSame(8, $profile['scoring']['element_value']);
        $this->assertSame(7, $profile['scoring']['modality_value']);
        $this->assertSame(-2, $profile['scoring']['tone_net']);
        $this->assertSame(['pozitív', 'negatív', 'pozitív'], $profile['tones']);
        $this->assertSame(['positive', 'shadow', 'positive'], $profile['variants']);
        $this->assertSame('scoring', $profile['draw_mode']);
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

        $this->assertSame('levegő', $profile['element']);
        $this->assertSame('fő', $profile['modality']);
        $this->assertSame('positive', $profile['variant']);
    }

    public function test_fallback_profile_has_defaults(): void
    {
        $profile = app(CardDrawProfileService::class)->fallback();

        $this->assertSame('leo', $profile['sun_sign']);
        $this->assertSame('tűz', $profile['element']);
        $this->assertSame('scoring', $profile['draw_mode']);
        $this->assertSame(['positive', 'positive', 'positive'], $profile['variants']);
        $this->assertSame([], $profile['chart']['objects']);
    }
}
