<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CardDrawProfileService;
use App\Services\EphemerisChartContext;
use App\Services\EphemerisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class EphemerisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(EphemerisChartContext::CACHE_KEY);
    }

    public function test_admin_ephemeris_page_renders_table_from_service(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(EphemerisService::class, function ($mock) {
            $mock->shouldReceive('calculate')->once()->andReturn([
                'datetime_utc' => '2026-08-09T12:00:00+00:00',
                'objects' => [
                    [
                        'name' => 'Sun',
                        'kind' => 'planet',
                        'longitude' => 137.5,
                        'sign' => 'Leo',
                        'degree' => 17.5,
                        'house' => 5,
                        'fixed_star' => null,
                        'elem' => 'tűz',
                        'elem_ertek' => 3.0,
                        'minoseg' => 'szilárd',
                        'minoseg_ertek' => 3.0,
                        'jegy_polaritas' => 'pozitív (aktív)',
                        'pozitiv_negativ_besorolas' => 'pozitív',
                        'pozitiv_negativ_ertek' => 1.5,
                        'polaritas_pont' => 5,
                        'polaritas_besorolas' => 'positive',
                        'polaritas_magyarazat' => 'Positive planet: +3. Positive sign (Leo): +2. Total: +5.',
                    ],
                    [
                        'name' => 'Moon',
                        'kind' => 'planet',
                        'longitude' => 150.1,
                        'sign' => 'Virgo',
                        'degree' => 0.1,
                        'fixed_star' => 'Regulus',
                        'elem' => 'föld',
                        'elem_ertek' => 2.0,
                        'minoseg' => 'változó',
                        'minoseg_ertek' => 2.0,
                        'jegy_polaritas' => 'negatív (befogadó)',
                        'pozitiv_negativ_besorolas' => 'pozitív',
                        'pozitiv_negativ_ertek' => 0.8,
                    ],
                ],
                'scoring' => [
                    'summary' => [
                        'elem_dominancia' => ['nev' => 'tűz', 'ertek' => 3],
                        'minoseg_dominancia' => ['nev' => 'szilárd', 'ertek' => 3],
                        'pozitiv_negativ' => ['nev' => 'pozitív', 'netto_ertek' => 2],
                    ],
                    'summary_clean' => [
                        'elem_dominancia' => ['nev' => 'tűz', 'ertek' => 3],
                        'minoseg_dominancia' => ['nev' => 'szilárd', 'ertek' => 3],
                        'pozitiv_negativ' => ['nev' => 'pozitív', 'netto_ertek' => 2],
                    ],
                    'fenyszogek' => [
                        ['left' => 'Sun', 'right' => 'Moon', 'kind' => 'opposition', 'points' => -1],
                    ],
                    'polaritas_osszesites' => [
                        'bolygok' => ['pont' => 5, 'besorolas' => 'positive'],
                        'jegyek' => ['pont' => 0, 'besorolas' => 'positive'],
                    ],
                    'tarot_helyek' => [
                        'positions' => [
                            ['hely' => 1, 'nev' => 'A jelenlegi helyzet lényege', 'besorolas' => 'pozitív', 'ertek' => 12],
                            ['hely' => 2, 'nev' => 'A ható erő vagy akadály', 'besorolas' => 'negatív', 'ertek' => -8],
                            ['hely' => 3, 'nev' => 'Tanács és várható irány', 'besorolas' => 'pozitív', 'ertek' => 5],
                        ],
                    ],
                ],
            ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.ephemeris.index', [
                'datetime_utc' => '2026-08-09T12:00',
                'lat' => 47.5,
                'lon' => 19.0,
            ]))
            ->assertOk()
            ->assertSee('type="datetime-local"', false)
            ->assertSee('Sun', false)
            ->assertSee('Leo', false)
            ->assertSee('Regulus', false)
            ->assertSee(__('admin.ephemeris.house'), false)
            ->assertSee(__('admin.ephemeris.aspects'), false)
            ->assertSee(__('admin.ephemeris.aspect_opposition'), false)
            ->assertSee('Positive planet: +3. Positive sign (Leo): +2. Total: +5.', false)
            ->assertSee(__('admin.ephemeris.summary'), false)
            ->assertSee(__('admin.ephemeris.summary_planets'), false)
            ->assertSee(__('admin.ephemeris.summary_signs'), false)
            ->assertSee('0', false)
            ->assertSee(__('admin.ephemeris.polarity_positive'), false)
            ->assertDontSee(__('admin.ephemeris.polarity_neutral'), false)
            ->assertSee(__('admin.ephemeris.explanation'), false)
            ->assertDontSee(__('admin.ephemeris.element_dominance'), false)
            ->assertDontSee(__('admin.ephemeris.modality_dominance'), false)
            ->assertDontSee(__('admin.ephemeris.tarot_slots'), false)
            ->assertDontSee('A ható erő vagy akadály', false)
            ->assertSee(__('admin.ephemeris.card_chart_fixed'), false);
    }

    public function test_admin_ephemeris_converts_datetime_local_to_utc_z(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(EphemerisService::class, function ($mock) {
            $mock->shouldReceive('calculate')
                ->once()
                ->with(Mockery::on(function (array $input): bool {
                    return ($input['datetime_utc'] ?? null) === '2026-03-21T15:30:00Z';
                }))
                ->andReturn([
                    'datetime_utc' => '2026-03-21T15:30:00+00:00',
                    'objects' => [],
                    'scoring' => null,
                ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.ephemeris.index', [
                'datetime_utc' => '2026-03-21T15:30',
                'lat' => 47.5,
                'lon' => 19.0,
            ]))
            ->assertOk()
            ->assertSee('value="2026-03-21T15:30"', false);
    }

    public function test_admin_now_mode_drives_homepage_card_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(EphemerisService::class, function ($mock) {
            $mock->shouldReceive('calculate')->andReturn([
                'datetime_utc' => '2026-01-01T00:00:00+00:00',
                'objects' => [
                    ['name' => 'Sun', 'sign' => 'Capricorn', 'degree' => 10, 'kind' => 'planet'],
                ],
                'scoring' => [
                    'summary_clean' => [
                        'elem_dominancia' => ['nev' => 'föld', 'ertek' => 4],
                        'minoseg_dominancia' => ['nev' => 'fő', 'ertek' => 4],
                        'pozitiv_negativ' => ['nev' => 'pozitív', 'netto_ertek' => 1],
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
        });

        $this->actingAs($admin)
            ->get(route('admin.ephemeris.index', [
                'datetime_utc' => '2026-01-01T00:00',
                'lat' => 47.5,
                'lon' => 19.0,
            ]))
            ->assertOk();

        $fixed = app(CardDrawProfileService::class)->forNow();
        $this->assertSame('fixed', $fixed['chart_mode']);
        $this->assertSame('2026-01-01T00:00:00Z', $fixed['datetime_utc']);
        $this->assertSame('capricorn', $fixed['sun_sign']);
        $this->assertSame(['positive', 'shadow', 'positive'], $fixed['variants']);

        $this->actingAs($admin)
            ->get(route('admin.ephemeris.index', ['moment_mode' => 'now']))
            ->assertOk()
            ->assertSee(__('admin.ephemeris.card_chart_now'), false);

        $this->assertTrue(app(EphemerisChartContext::class)->isNow());
    }

    public function test_coordinates_follow_residence_and_fall_back_to_budapest(): void
    {
        $admin = User::factory()->admin()->create([
            'residence_country' => 'Ausztria',
            'residence_city' => 'Bécs',
            'residence_latitude' => '48.208353',
            'residence_longitude' => '16.372504',
        ]);

        $this->mock(EphemerisService::class, function ($mock) {
            $mock->shouldReceive('calculate')
                ->once()
                ->with(Mockery::on(function (array $input): bool {
                    return abs((float) $input['lat'] - 48.208353) < 0.000001
                        && abs((float) $input['lon'] - 16.372504) < 0.000001;
                }))
                ->andReturn([
                    'datetime_utc' => '2026-08-09T12:00:00+00:00',
                    'objects' => [],
                    'scoring' => null,
                ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.ephemeris.index'))
            ->assertOk()
            ->assertSee('Bécs, Ausztria', false)
            ->assertSee('value="48.208353"', false)
            ->assertSee('value="16.372504"', false);

        $guestPlace = User::factory()->admin()->create();

        $this->mock(EphemerisService::class, function ($mock) {
            $mock->shouldReceive('calculate')
                ->once()
                ->with(Mockery::on(function (array $input): bool {
                    return abs((float) $input['lat'] - 47.4979) < 0.000001
                        && abs((float) $input['lon'] - 19.0402) < 0.000001;
                }))
                ->andReturn([
                    'datetime_utc' => '2026-08-09T12:00:00+00:00',
                    'objects' => [],
                    'scoring' => null,
                ]);
        });

        $this->actingAs($guestPlace)
            ->get(route('admin.ephemeris.index'))
            ->assertOk()
            ->assertSee('Budapest', false)
            ->assertSee('value="47.4979"', false);
    }
}
