<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EphemerisService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EphemerisController extends Controller
{
    public function index(Request $request, EphemerisService $ephemeris): View
    {
        $validated = $request->validate([
            'datetime_utc' => ['nullable', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'sidereal' => ['nullable', 'boolean'],
        ]);

        $input = [
            'datetime_utc' => $validated['datetime_utc'] ?? now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'lat' => $validated['lat'] ?? config('ephemeris.default_lat'),
            'lon' => $validated['lon'] ?? config('ephemeris.default_lon'),
            'sidereal' => filter_var($validated['sidereal'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        $result = null;
        $error = null;

        try {
            $result = $ephemeris->calculate($input);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        return view('admin.ephemeris.index', [
            'input' => $input,
            'result' => $result,
            'error' => $error,
        ]);
    }
}
