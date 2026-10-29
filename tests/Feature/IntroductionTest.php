<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Services\CardDrawProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntroductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_introduction_page_is_a_menu_destination_and_saves_profile(): void
    {
        Template::factory()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);
        $this->mock(CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív'],
                'variants' => ['positive'],
                'slots' => ['leo'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-10T12:00:00Z',
            ]);
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(__('messages.intro_menu'), false)
            ->assertSee(route('account.introduction'), false);

        $this->actingAs($user)
            ->get(route('account.introduction'))
            ->assertOk()
            ->assertSee(__('messages.intro_text'), false)
            ->assertSee(__('messages.intro_birth'), false)
            ->assertDontSee('budapesti idő', false)
            ->assertSee('data-place-combo', false)
            ->assertSee('Magyarország', false);

        $this->actingAs($user)
            ->put(route('account.introduction.update'), [
                'birth_at' => '1990-05-01T14:30',
                'gender' => 'female',
                'introduction' => 'Kertész vagyok, és a döntéseimet lassan hozom.',
            ])
            ->assertRedirect(route('account.introduction'));

        $user->refresh();
        $this->assertSame('female', $user->gender);
        $this->assertSame('Kertész vagyok, és a döntéseimet lassan hozom.', $user->introduction);
        $this->assertSame('1990-05-01 12:30:00', $user->birth_at?->utc()->format('Y-m-d H:i:s'));
    }

    public function test_introduction_rejects_more_than_500_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.introduction'))
            ->put(route('account.introduction.update'), [
                'introduction' => str_repeat('á', 501),
            ])
            ->assertRedirect(route('account.introduction'))
            ->assertSessionHasErrors('introduction');
    }

    public function test_places_are_geocoded_saved_and_shown(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '47.497994', 'lon' => '19.040359'],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.introduction.update'), [
                'birth_country' => 'Magyarország',
                'birth_city' => 'Budapest',
                'residence_country' => 'Ausztria',
                'residence_city' => 'Bécs',
            ])
            ->assertRedirect(route('account.introduction'))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Magyarország', $user->birth_country);
        $this->assertSame('Budapest', $user->birth_city);
        $this->assertSame('47.497994', $user->birth_latitude);
        $this->assertSame('19.040359', $user->birth_longitude);
        $this->assertSame('47.497994', $user->residence_latitude);

        $this->actingAs($user)
            ->get(route('account.introduction'))
            ->assertOk()
            ->assertSee('Születési ország', false)
            ->assertSee('Tartózkodási város', false)
            ->assertSee('Szélesség: 47.497994', false)
            ->assertSee('Hosszúság: 19.040359', false)
            ->assertSee('data-code="HU"', false)
            ->assertSee('data-code="AT"', false);
    }

    public function test_city_search_returns_settlements_for_a_known_country(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    ['name' => 'Budapest', 'feature_code' => 'PPLC'],
                    ['name' => 'Budapest', 'feature_code' => 'PPLC'],
                    ['name' => 'Budapest Liszt Ferenc Nemzetközi Repülőtér', 'feature_code' => 'AIRP'],
                    ['name' => 'Budaörs', 'feature_code' => 'PPL'],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('account.introduction.cities', ['country' => 'HU', 'q' => 'Bud']))
            ->assertOk()
            ->assertExactJson(['cities' => ['Budapest', 'Budaörs']]);

        $this->actingAs($user)
            ->getJson(route('account.introduction.cities', ['country' => 'ZZ', 'q' => 'Bud']))
            ->assertOk()
            ->assertExactJson(['cities' => []]);
    }
}
