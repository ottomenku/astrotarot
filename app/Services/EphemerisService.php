<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class EphemerisService
{
    public function __construct(
        private readonly AstroScoringEngine $scoring,
        private readonly TarotPositionPolarity $positionPolarity,
    ) {}

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

        return $this->enrichWithScoring($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enrichWithScoring(array $data): array
    {
        $objects = $data['objects'] ?? [];
        if (! is_array($objects) || $objects === []) {
            $data['scoring'] = null;

            return $data;
        }

        $scoreableIndexes = [];
        $records = [];

        foreach ($objects as $index => $object) {
            if (! is_array($object)) {
                continue;
            }

            if (($object['kind'] ?? '') === 'fixed_star') {
                continue;
            }

            $name = $object['name'] ?? null;
            $sign = $object['sign'] ?? null;
            $degree = $object['degree'] ?? null;

            if (! is_string($name) || ! is_string($sign) || ! is_numeric($degree)) {
                continue;
            }

            $scoreableIndexes[] = $index;
            $records[] = [
                'object' => $name,
                'sign' => $sign,
                'degree' => $this->clampDegree((float) $degree),
                'retrograde' => (bool) ($object['retrograde'] ?? false),
                'longitude' => isset($object['longitude']) ? (float) $object['longitude'] : null,
                'house' => isset($object['house']) ? (int) $object['house'] : null,
            ];
        }

        if ($records === []) {
            $data['scoring'] = null;

            return $data;
        }

        try {
            $analysis = $this->scoring->analyze($records);
        } catch (Throwable $e) {
            Log::warning('Astro scoring failed', ['error' => $e->getMessage()]);
            $data['scoring'] = null;

            return $data;
        }

        foreach ($analysis['records'] as $offset => $scored) {
            $index = $scoreableIndexes[$offset];
            $objects[$index] = array_merge($objects[$index], [
                'elem' => $scored['elem'],
                'elem_ertek' => $scored['elem_ertek'],
                'minoseg' => $scored['minoseg'],
                'minoseg_ertek' => $scored['minoseg_ertek'],
                'jegy_polaritas' => $scored['jegy_polaritas'],
                'pozitiv_pont' => $scored['pozitiv_pont'],
                'negativ_pont' => $scored['negativ_pont'],
                'pozitiv_negativ_besorolas' => $scored['pozitiv_negativ_besorolas'],
                'pozitiv_negativ_ertek' => $scored['pozitiv_negativ_ertek'],
                'magyarazat' => $scored['magyarazat'],
            ]);
        }

        $data['objects'] = $objects;

        $tarotPositions = null;
        try {
            $tarotPositions = $this->positionPolarity->assign($analysis);
        } catch (Throwable $e) {
            Log::warning('Tarot position polarity failed', ['error' => $e->getMessage()]);
        }

        $data['scoring'] = [
            'summary' => $analysis['summary'],
            'summary_clean' => $analysis['summary_clean'],
            'tarot_helyek' => $tarotPositions,
            'aspects' => $analysis['aspects'],
            'model' => $analysis['model'],
        ];

        return $data;
    }

    private function clampDegree(float $degree): float
    {
        if ($degree < 0.0) {
            return 0.0;
        }

        if ($degree >= 30.0) {
            return 29.9999;
        }

        return $degree;
    }
}
