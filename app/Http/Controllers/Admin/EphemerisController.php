<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EphemerisChartContext;
use App\Services\EphemerisService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class EphemerisController extends Controller
{
    public function index(Request $request, EphemerisService $ephemeris, EphemerisChartContext $chartContext): View
    {
        $validated = $request->validate([
            'datetime_utc' => ['nullable', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'sidereal' => ['nullable', 'boolean'],
            'moment_mode' => ['nullable', 'in:now,fixed'],
        ]);

        $place = $this->resolvedPlace($request->user());

        if (($validated['moment_mode'] ?? null) === 'now') {
            $chartContext->useNow();
        } elseif ($request->filled('datetime_utc')) {
            $moment = $this->resolveMoment($validated['datetime_utc']);
            $chartContext->useFixed([
                'datetime_utc' => $moment->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'lat' => $validated['lat'] ?? $place['lat'],
                'lon' => $validated['lon'] ?? $place['lon'],
                'sidereal' => filter_var($validated['sidereal'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $chart = $chartContext->resolve();
        $moment = $this->resolveMoment($chart['datetime_utc']);
        $lat = $chart['mode'] === 'now' ? $place['lat'] : (float) $chart['lat'];
        $lon = $chart['mode'] === 'now' ? $place['lon'] : (float) $chart['lon'];

        $input = [
            'datetime_utc' => $moment->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'datetime_local' => $moment->copy()->utc()->format('Y-m-d\TH:i'),
            'lat' => $this->formatCoord($lat),
            'lon' => $this->formatCoord($lon),
            'sidereal' => $chart['sidereal'],
        ];

        $result = null;
        $error = null;

        try {
            $result = $ephemeris->calculate([
                'datetime_utc' => $input['datetime_utc'],
                'lat' => $input['lat'],
                'lon' => $input['lon'],
                'sidereal' => $input['sidereal'],
            ]);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        return view('admin.ephemeris.index', [
            'input' => $input,
            'result' => $result,
            'error' => $error,
            'chartMode' => $chart['mode'],
            'chartDatetimeUtc' => $chart['datetime_utc'],
            'locationLabel' => $this->locationLabel($place, $lat, $lon),
        ]);
    }

    /**
     * @return array{lat: float, lon: float, label: string}
     */
    private function resolvedPlace(?User $user): array
    {
        if ($user?->residence_latitude !== null && $user?->residence_longitude !== null) {
            $city = trim((string) $user->residence_city);
            $country = trim((string) $user->residence_country);
            $label = $city !== ''
                ? ($country !== '' ? $city.', '.$country : $city)
                : __('admin.ephemeris.residence');

            return [
                'lat' => (float) $user->residence_latitude,
                'lon' => (float) $user->residence_longitude,
                'label' => $label,
            ];
        }

        return [
            'lat' => (float) config('ephemeris.default_lat'),
            'lon' => (float) config('ephemeris.default_lon'),
            'label' => 'Budapest',
        ];
    }

    /**
     * @param  array{lat: float, lon: float, label: string}  $place
     */
    private function locationLabel(array $place, float $lat, float $lon): string
    {
        if ($this->sameCoord($lat, $place['lat']) && $this->sameCoord($lon, $place['lon'])) {
            return $place['label'];
        }

        if ($this->sameCoord($lat, (float) config('ephemeris.default_lat')) && $this->sameCoord($lon, (float) config('ephemeris.default_lon'))) {
            return 'Budapest';
        }

        return __('admin.ephemeris.custom_place');
    }

    private function sameCoord(float $left, float $right): bool
    {
        return abs($left - $right) < 0.000001;
    }

    private function formatCoord(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    private function resolveMoment(?string $value): Carbon
    {
        if ($value === null || trim($value) === '') {
            return now('UTC');
        }

        try {
            return Carbon::parse($value, 'UTC')->utc();
        } catch (Throwable) {
            return now('UTC');
        }
    }
}
