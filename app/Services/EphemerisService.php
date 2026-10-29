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
        $data = $this->run([
            'datetime_utc' => $input['datetime_utc'],
            'lat' => (float) ($input['lat'] ?? config('ephemeris.default_lat')),
            'lon' => (float) ($input['lon'] ?? config('ephemeris.default_lon')),
            'sidereal' => (bool) ($input['sidereal'] ?? false),
            'ayanamsa' => $input['ayanamsa'] ?? 'lahiri',
            'conjunction_orb' => (float) ($input['conjunction_orb'] ?? config('ephemeris.conjunction_orb')),
        ]);

        if (! isset($data['objects']) || ! is_array($data['objects'])) {
            throw new RuntimeException('Invalid ephemeris response.');
        }

        return $this->enrichWithScoring($data);
    }

    /**
     * Scan a date window for an aspect, a house stay, or retrograde motion.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function project(array $input): array
    {
        return $this->run(array_merge($input, ['mode' => 'projection']));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function run(array $payload): array
    {
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

        if (! is_array($data)) {
            throw new RuntimeException('Invalid ephemeris response.');
        }

        return $data;
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
            $data['objects'] = $objects;
            $data['scoring'] = null;

            return $this->attachPolarity($data, $records);
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

        return $this->attachPolarity($data, $records);
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

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function attachPolarity(array $data, array $records): array
    {
        $polarity = $this->scoring->polarity($records);
        $byName = [];
        foreach ($polarity['planets'] as $planet) {
            $byName[$planet['name']] = $planet;
        }

        $objects = $data['objects'] ?? [];
        foreach ($objects as $index => $object) {
            if (! is_array($object)) {
                continue;
            }
            $planet = $byName[$object['name'] ?? ''] ?? null;
            if ($planet === null) {
                continue;
            }
            $objects[$index]['polaritas_pont'] = $planet['total'];
            $objects[$index]['polaritas_besorolas'] = $planet['class'];
            $objects[$index]['polaritas_magyarazat'] = $this->explainPlanet($planet);
        }
        $data['objects'] = $objects;

        $signs = [];
        foreach ($polarity['signs'] as $sign) {
            $signs[] = [
                'kulcs' => $sign['sign_key'],
                'nev' => $this->signLabel($sign['sign_key'], $sign['sign_name']),
                'pont' => $sign['total'],
                'besorolas' => $sign['class'],
                'magyarazat' => $this->explainSign($sign),
            ];
        }

        $scoring = is_array($data['scoring'] ?? null) ? $data['scoring'] : [];
        $scoring['jegyek'] = $signs;
        $scoring['fenyszogek'] = $polarity['aspects'];
        $scoring['polaritas_osszesites'] = [
            'bolygok' => [
                'pont' => $polarity['summary']['planets']['total'],
                'besorolas' => $polarity['summary']['planets']['class'],
            ],
            'jegyek' => [
                'pont' => $polarity['summary']['signs']['total'],
                'besorolas' => $polarity['summary']['signs']['class'],
            ],
        ];
        $data['scoring'] = $scoring;

        return $data;
    }

    /** @param  array<string, mixed>  $planet */
    private function explainPlanet(array $planet): string
    {
        $parts = [
            __('admin.ephemeris.polarity_planet_'.$planet['nature_key'], [
                'points' => $this->signed((int) $planet['nature_points']),
            ]),
            __('admin.ephemeris.'.($planet['sign_positive'] ? 'polarity_sign_bonus_positive' : 'polarity_sign_bonus_negative'), [
                'sign' => $this->signLabel((string) $planet['sign_key'], (string) $planet['sign_name']),
                'points' => $this->signed((int) $planet['sign_points']),
            ]),
        ];

        $aspects = $planet['aspects'] ?? [];
        if ($aspects === []) {
            $parts[] = __('admin.ephemeris.polarity_no_aspects');
        } else {
            foreach ($aspects as $aspect) {
                $parts[] = __('admin.ephemeris.polarity_aspect', [
                    'aspect' => __('admin.ephemeris.aspect_'.$aspect['kind']),
                    'with' => $aspect['with'],
                    'points' => $this->signed((int) $aspect['points']),
                ]);
            }
        }

        $parts[] = __('admin.ephemeris.polarity_total', [
            'total' => $this->signed((int) $planet['total']),
        ]);

        return implode(' ', $parts);
    }

    /** @param  array<string, mixed>  $sign */
    private function explainSign(array $sign): string
    {
        $parts = [
            __('admin.ephemeris.'.($sign['positive'] ? 'polarity_sign_base_positive' : 'polarity_sign_base_negative'), [
                'points' => $this->signed((int) $sign['base_points']),
            ]),
        ];

        $planets = $sign['planets'] ?? [];
        if ($planets === []) {
            $parts[] = __('admin.ephemeris.polarity_sign_empty');
        } else {
            $list = [];
            foreach ($planets as $planet) {
                $list[] = $planet['name'].' '.$this->signed((int) $planet['points']);
            }
            $parts[] = __('admin.ephemeris.polarity_sign_planets', [
                'list' => implode(', ', $list),
            ]);
        }

        $parts[] = __('admin.ephemeris.polarity_total', [
            'total' => $this->signed((int) $sign['total']),
        ]);

        return implode(' ', $parts);
    }

    private function signLabel(string $key, string $fallback): string
    {
        $labels = [
            'kos' => ['hu' => 'Kos', 'en' => 'Aries'],
            'bika' => ['hu' => 'Bika', 'en' => 'Taurus'],
            'ikrek' => ['hu' => 'Ikrek', 'en' => 'Gemini'],
            'rak' => ['hu' => 'Rák', 'en' => 'Cancer'],
            'oroszlan' => ['hu' => 'Oroszlán', 'en' => 'Leo'],
            'szuz' => ['hu' => 'Szűz', 'en' => 'Virgo'],
            'merleg' => ['hu' => 'Mérleg', 'en' => 'Libra'],
            'skorpio' => ['hu' => 'Skorpió', 'en' => 'Scorpio'],
            'nyilas' => ['hu' => 'Nyilas', 'en' => 'Sagittarius'],
            'bak' => ['hu' => 'Bak', 'en' => 'Capricorn'],
            'vizonto' => ['hu' => 'Vízöntő', 'en' => 'Aquarius'],
            'halak' => ['hu' => 'Halak', 'en' => 'Pisces'],
        ];
        $locale = app()->getLocale() === 'en' ? 'en' : 'hu';

        return $labels[$key][$locale] ?? $fallback;
    }

    private function signed(int $value): string
    {
        return $value > 0 ? '+'.$value : (string) $value;
    }
}
