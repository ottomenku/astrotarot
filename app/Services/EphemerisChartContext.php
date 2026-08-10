<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class EphemerisChartContext
{
    public const CACHE_KEY = 'ephemeris.card_chart';

    /**
     * @return array{
     *     mode: 'now'|'fixed',
     *     datetime_utc: string,
     *     lat: float,
     *     lon: float,
     *     sidereal: bool
     * }
     */
    public function resolve(): array
    {
        $stored = Cache::get(self::CACHE_KEY, ['mode' => 'now']);
        $defaults = [
            'lat' => (float) config('ephemeris.default_lat'),
            'lon' => (float) config('ephemeris.default_lon'),
            'sidereal' => false,
        ];

        if (($stored['mode'] ?? 'now') === 'fixed' && ! empty($stored['datetime_utc'])) {
            return [
                'mode' => 'fixed',
                'datetime_utc' => (string) $stored['datetime_utc'],
                'lat' => (float) ($stored['lat'] ?? $defaults['lat']),
                'lon' => (float) ($stored['lon'] ?? $defaults['lon']),
                'sidereal' => (bool) ($stored['sidereal'] ?? false),
            ];
        }

        return [
            'mode' => 'now',
            'datetime_utc' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'lat' => $defaults['lat'],
            'lon' => $defaults['lon'],
            'sidereal' => false,
        ];
    }

    /**
     * @param  array{
     *     datetime_utc: string,
     *     lat?: float|int|string,
     *     lon?: float|int|string,
     *     sidereal?: bool
     * }  $input
     */
    public function useFixed(array $input): void
    {
        Cache::forever(self::CACHE_KEY, [
            'mode' => 'fixed',
            'datetime_utc' => $input['datetime_utc'],
            'lat' => (float) ($input['lat'] ?? config('ephemeris.default_lat')),
            'lon' => (float) ($input['lon'] ?? config('ephemeris.default_lon')),
            'sidereal' => (bool) ($input['sidereal'] ?? false),
        ]);
    }

    public function useNow(): void
    {
        Cache::forever(self::CACHE_KEY, ['mode' => 'now']);
    }

    public function isNow(): bool
    {
        $stored = Cache::get(self::CACHE_KEY, ['mode' => 'now']);

        return ($stored['mode'] ?? 'now') !== 'fixed';
    }
}
