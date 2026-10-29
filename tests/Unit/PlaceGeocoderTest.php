<?php

namespace Tests\Unit;

use App\Services\PlaceGeocoder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceGeocoderTest extends TestCase
{
    public function test_city_and_country_become_coordinates(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '47.4979937', 'lon' => '19.0403594'],
            ]),
        ]);

        $coords = app(PlaceGeocoder::class)->coordinates('Magyarország', 'Budapest');

        $this->assertSame(47.497994, $coords['latitude']);
        $this->assertSame(19.040359, $coords['longitude']);
    }

    public function test_unknown_place_returns_null(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([]),
        ]);

        $this->assertNull(app(PlaceGeocoder::class)->coordinates('Seholország', 'Nincsilyen'));
    }

    public function test_city_search_uses_the_country_code_and_skips_duplicates(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    ['name' => 'Bécs', 'feature_code' => 'PPLC'],
                    ['name' => 'Bécs', 'feature_code' => 'PPLC'],
                    ['name' => 'Bécs-Schwechati nemzetközi repülőtér', 'feature_code' => 'AIRP'],
                    ['name' => 'Bécsújhely', 'feature_code' => 'PPLA3'],
                ],
            ]),
        ]);

        $cities = app(PlaceGeocoder::class)->cities('AT', 'Béc');

        $this->assertSame(['Bécs', 'Bécsújhely'], $cities);
        Http::assertSent(function ($request) {
            return $request['name'] === 'Béc'
                && $request['countryCode'] === 'AT'
                && $request['language'] === 'hu';
        });
    }
}
