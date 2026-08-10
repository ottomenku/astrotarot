<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        if (($validated['moment_mode'] ?? null) === 'now') {
            $chartContext->useNow();
        } elseif ($request->filled('datetime_utc')) {
            $moment = $this->resolveMoment($validated['datetime_utc']);
            $chartContext->useFixed([
                'datetime_utc' => $moment->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'lat' => $validated['lat'] ?? config('ephemeris.default_lat'),
                'lon' => $validated['lon'] ?? config('ephemeris.default_lon'),
                'sidereal' => filter_var($validated['sidereal'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $chart = $chartContext->resolve();
        $moment = $this->resolveMoment($chart['datetime_utc']);

        $input = [
            'datetime_utc' => $moment->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'datetime_local' => $moment->copy()->utc()->format('Y-m-d\TH:i'),
            'lat' => $chart['lat'],
            'lon' => $chart['lon'],
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
        ]);
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
