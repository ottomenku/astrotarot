<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PlaceGeocoder
{
    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function coordinates(string $country, string $city): ?array
    {
        $country = $this->clean($country);
        $city = $this->clean($city);
        if ($country === '' || $city === '') {
            return null;
        }

        $key = 'geocode:'.md5(mb_strtolower($country).'|'.mb_strtolower($city));
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['latitude'], $cached['longitude'])) {
            return [
                'latitude' => (float) $cached['latitude'],
                'longitude' => (float) $cached['longitude'],
            ];
        }

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => config('app.name').'/1.0 ('.config('app.url').')',
                'Accept' => 'application/json',
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'jsonv2',
                'city' => $city,
                'country' => $country,
                'limit' => 1,
            ]);

        if (! $response->ok()) {
            return null;
        }

        $row = $response->json()[0] ?? null;
        if (! is_array($row) || ! is_numeric($row['lat'] ?? null) || ! is_numeric($row['lon'] ?? null)) {
            return null;
        }

        $coords = [
            'latitude' => round((float) $row['lat'], 6),
            'longitude' => round((float) $row['lon'], 6),
        ];
        Cache::put($key, $coords, now()->addDays(30));

        return $coords;
    }

    /**
     * Prefix matches for a country. Nominatim only resolves a finished place name.
     *
     * @return list<string>
     */
    public function cities(string $countryCode, string $query): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $query = $this->clean($query);
        if (strlen($countryCode) !== 2 || mb_strlen($query) < 2) {
            return [];
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'hu';
        $key = 'place-cities:'.md5($countryCode.'|'.mb_strtolower($query).'|'.$locale);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return array_values(array_filter($cached, 'is_string'));
        }

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => config('app.name').'/1.0 ('.config('app.url').')',
                'Accept' => 'application/json',
            ])
            ->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $query,
                'count' => 20,
                'language' => $locale,
                'countryCode' => $countryCode,
                'format' => 'json',
            ]);

        if (! $response->ok()) {
            return [];
        }

        $names = [];
        foreach ((array) ($response->json('results') ?? []) as $row) {
            if (! is_array($row) || ! $this->isSettlement((string) ($row['feature_code'] ?? ''))) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || in_array($name, $names, true)) {
                continue;
            }
            $names[] = mb_substr($name, 0, 80);
            if (count($names) >= 8) {
                break;
            }
        }

        Cache::put($key, $names, now()->addDay());

        return $names;
    }

    private function isSettlement(string $featureCode): bool
    {
        return str_starts_with($featureCode, 'PPL') && $featureCode !== 'PPLX';
    }

    private function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, 80);
    }
}
