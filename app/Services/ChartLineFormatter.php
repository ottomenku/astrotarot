<?php

namespace App\Services;

/**
 * Compact chart lines for the reading prompt.
 *
 * Positions follow a fixed order. An aspect row lists only earlier bodies,
 * so each pair appears once. Fixed stars never open a row; a conjunction
 * is written on the body it touches.
 */
class ChartLineFormatter
{
    /** @var list<string> */
    private const ORDER = [
        'Sun', 'Moon', 'Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn',
        'Uranus', 'Neptune', 'Pluto', 'ASC', 'DESC', 'Fortune',
    ];

    /** @var list<string> */
    private const SIGNS = [
        'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
        'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces',
    ];

    /** @var array<string, array{0: float, 1: float}> */
    private const ASPECTS = [
        'conjunction' => [0.0, 8.0],
        'sextile' => [60.0, 4.0],
        'square' => [90.0, 6.0],
        'trine' => [120.0, 6.0],
        'quincunx' => [150.0, 3.0],
        'opposition' => [180.0, 8.0],
    ];

    /**
     * @param  array<string, mixed>  $chart
     * @return array{positions: string, aspects: string}
     */
    public function fromChart(array $chart): array
    {
        $byName = [];
        foreach ($chart['objects'] ?? [] as $object) {
            if (! is_array($object)) {
                continue;
            }
            $name = (string) ($object['name'] ?? '');
            if ($name !== '') {
                $byName[$name] = $object;
            }
        }

        $asc = $byName['ASC'] ?? null;
        $sun = $byName['Sun'] ?? null;
        $moon = $byName['Moon'] ?? null;
        if (is_array($asc) && isset($asc['longitude'])) {
            $ascLon = (float) $asc['longitude'];
            $byName['DESC'] = $this->point('DESC', $this->norm($ascLon + 180.0), 7);
            if (is_array($sun) && is_array($moon) && isset($sun['longitude'], $moon['longitude'])) {
                $day = (int) ($sun['house'] ?? 0) >= 7;
                $fortune = $this->norm($ascLon + ($day
                    ? (float) $moon['longitude'] - (float) $sun['longitude']
                    : (float) $sun['longitude'] - (float) $moon['longitude']));
                $byName['Fortune'] = $this->point(
                    'Fortune',
                    $fortune,
                    $this->houseOf($fortune, is_array($chart['houses'] ?? null) ? $chart['houses'] : []),
                );
            }
        }

        return $this->render($byName);
    }

    /**
     * Positions from an already shortened planet list. Aspects need longitudes.
     *
     * @param  list<array<string, mixed>>  $planets
     * @return array{positions: string, aspects: string}
     */
    public function fromPresented(array $planets): array
    {
        $byName = [];
        foreach ($planets as $planet) {
            if (! is_array($planet)) {
                continue;
            }
            $name = (string) ($planet['name'] ?? '');
            if ($name !== '') {
                $byName[$name] = $planet;
            }
        }

        return $this->render($byName);
    }

    /**
     * @param  array<string, array<string, mixed>>  $byName
     * @return array{positions: string, aspects: string}
     */
    private function render(array $byName): array
    {
        $rows = [];
        foreach (self::ORDER as $name) {
            if (isset($byName[$name])) {
                $rows[] = $byName[$name];
            }
        }

        $positions = [];
        $aspects = [];
        foreach ($rows as $index => $row) {
            $positions[] = $this->position($row);
            $parts = [];
            $longitude = $row['longitude'] ?? null;
            if (is_numeric($longitude)) {
                for ($earlier = 0; $earlier < $index; $earlier++) {
                    $otherLon = $rows[$earlier]['longitude'] ?? null;
                    if (! is_numeric($otherLon)) {
                        continue;
                    }
                    $aspect = $this->aspect((float) $longitude, (float) $otherLon);
                    if ($aspect !== null) {
                        $parts[] = $aspect.' '.(string) ($rows[$earlier]['name'] ?? '');
                    }
                }
            }
            $star = trim((string) ($row['fixed_star'] ?? ''));
            if ($star !== '') {
                $parts[] = 'conjunction '.$star;
            }
            if ($parts !== []) {
                $aspects[] = (string) ($row['name'] ?? '').': '.implode(', ', $parts);
            }
        }

        return [
            'positions' => implode('; ', $positions),
            'aspects' => implode('; ', $aspects),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function position(array $row): string
    {
        $name = (string) ($row['name'] ?? '');
        $sign = (string) ($row['sign'] ?? '');
        $degree = is_numeric($row['degree'] ?? null)
            ? (float) $row['degree']
            : (is_numeric($row['longitude'] ?? null) ? $this->signDegree((float) $row['longitude']) : 0.0);
        $house = is_numeric($row['house'] ?? null) ? (string) (int) $row['house'] : '';
        $place = $name.','.$sign.','.number_format($degree, 1, '.', '').','.$house;
        if (in_array($name, ['ASC', 'DESC', 'Fortune'], true)) {
            return $place;
        }

        return $place.','.((bool) ($row['retrograde'] ?? false) ? 'R' : 'N');
    }

    /**
     * @return array{name: string, longitude: float, sign: string, degree: float, house: int}
     */
    private function point(string $name, float $longitude, ?int $house): array
    {
        return [
            'name' => $name,
            'longitude' => $longitude,
            'sign' => self::SIGNS[(int) floor($longitude / 30.0) % 12],
            'degree' => $this->signDegree($longitude),
            'house' => $house ?? 0,
        ];
    }

    private function aspect(float $a, float $b): ?string
    {
        $distance = $this->distance($a, $b);
        foreach (self::ASPECTS as $name => $spec) {
            if (abs($distance - $spec[0]) <= $spec[1]) {
                return $name;
            }
        }

        return null;
    }

    private function distance(float $a, float $b): float
    {
        $delta = abs($this->norm($a) - $this->norm($b));

        return min($delta, 360.0 - $delta);
    }

    private function signDegree(float $longitude): float
    {
        return $this->norm($longitude) - (floor($this->norm($longitude) / 30.0) * 30.0);
    }

    private function norm(float $longitude): float
    {
        $value = fmod($longitude, 360.0);
        if ($value < 0) {
            $value += 360.0;
        }

        return $value;
    }

    /**
     * @param  list<array<string, mixed>>  $houses
     */
    private function houseOf(float $longitude, array $houses): ?int
    {
        $cusps = [];
        foreach ($houses as $house) {
            if (! is_array($house) || ! is_numeric($house['longitude'] ?? null)) {
                continue;
            }
            $cusps[] = (float) $house['longitude'];
        }
        if (count($cusps) < 12) {
            return null;
        }

        $lon = $this->norm($longitude);
        for ($i = 0; $i < 12; $i++) {
            $start = $this->norm($cusps[$i]);
            $end = $this->norm($cusps[($i + 1) % 12]);
            if ($start <= $end) {
                if ($lon >= $start && $lon < $end) {
                    return $i + 1;
                }
            } elseif ($lon >= $start || $lon < $end) {
                return $i + 1;
            }
        }

        return 1;
    }
}
