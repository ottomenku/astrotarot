<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class EphemerisService
{
    /**
     * @param  array{
     *     datetime_utc: string,
     *     lat?: float|int|string,
     *     lon?: float|int|string,
     *     sidereal?: bool,
     *     ayanamsa?: string,
     *     conjunction_orb?: float|int
     * }  $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $payload = [
            'datetime_utc' => $input['datetime_utc'],
            'lat' => (float) ($input['lat'] ?? config('ephemeris.default_lat')),
            'lon' => (float) ($input['lon'] ?? config('ephemeris.default_lon')),
            'sidereal' => (bool) ($input['sidereal'] ?? false),
            'ayanamsa' => $input['ayanamsa'] ?? 'lahiri',
            'conjunction_orb' => (float) ($input['conjunction_orb'] ?? config('ephemeris.conjunction_orb')),
        ];

        $python = (string) config('ephemeris.python_bin');
        $script = (string) config('ephemeris.script');

        if (! is_file($script)) {
            throw new RuntimeException('Ephemeris script not found.');
        }

        $process = new Process([$python, $script]);
        $process->setTimeout((int) config('ephemeris.timeout', 30));
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $process->run();

        if (! $process->isSuccessful()) {
            Log::error('Ephemeris calculation failed', [
                'python' => $python,
                'script' => $script,
                'error' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);

            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Ephemeris calculation failed.');
        }

        $data = json_decode($process->getOutput(), true);

        if (! is_array($data) || ! isset($data['objects']) || ! is_array($data['objects'])) {
            throw new RuntimeException('Invalid ephemeris response.');
        }

        return $data;
    }
}
