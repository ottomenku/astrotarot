<?php

namespace App\Services;

/**
 * Chart lines for a classified reading.
 *
 * A full chart lists each body once. Its relations name only bodies later
 * in the fixed order, at the nominal angle. A focused body lists every
 * aspect, because that slice is not part of the full list.
 */
class SkyBrief
{
    /** @var list<string> */
    private const ORDER = [
        'Sun', 'Moon', 'Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn',
        'Uranus', 'Neptune', 'Pluto', 'ASC', 'DESC', 'Fortune',
    ];

    /** @var list<string> */
    private const SIGNS_EN = [
        'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
        'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces',
    ];

    /** @var list<string> */
    private const SIGNS_HU = [
        'Kos', 'Bika', 'Ikrek', 'Rák', 'Oroszlán', 'Szűz',
        'Mérleg', 'Skorpió', 'Nyilas', 'Bak', 'Vízöntő', 'Halak',
    ];

    /** @var array<string, string> */
    private const BODY_HU = [
        'Sun' => 'Nap',
        'Moon' => 'Hold',
        'Mercury' => 'Merkúr',
        'Venus' => 'Vénusz',
        'Mars' => 'Mars',
        'Jupiter' => 'Jupiter',
        'Saturn' => 'Szaturnusz',
        'Uranus' => 'Uránusz',
        'Neptune' => 'Neptunusz',
        'Pluto' => 'Plútó',
        'ASC' => 'ASC',
        'DESC' => 'DESC',
        'Fortune' => 'Fortune',
    ];

    /** @var list<array{0: int, 1: float, 2: float}> */
    private const ASPECTS = [
        [0, 0.0, 8.0],
        [60, 60.0, 4.0],
        [90, 90.0, 6.0],
        [120, 120.0, 6.0],
        [150, 150.0, 3.0],
        [180, 180.0, 8.0],
    ];

    /** @var list<string> */
    private const POINTS = ['ASC', 'DESC', 'Fortune'];

    /**
     * @param  array<string, mixed>  $chart
     */
    public function positions(array $chart, string $locale): string
    {
        $lines = [];
        foreach ($this->rows($chart) as $row) {
            $lines[] = $this->bodyName((string) $row['name'], $locale).': '.$this->place($row, $locale, true);
        }

        return $lines === [] ? '—' : implode('. ', $lines);
    }

    /**
     * @param  array<string, mixed>  $chart
     */
    public function relations(array $chart, string $locale): string
    {
        $rows = $this->rows($chart);
        $lines = [];
        foreach ($rows as $index => $row) {
            $parts = $this->aspectParts($rows, $index, $locale, false);
            if ($parts === []) {
                continue;
            }
            $lines[] = $this->bodyName((string) $row['name'], $locale).': '.implode('; ', $parts);
        }

        return $lines === [] ? '—' : implode("\n", $lines);
    }

    /**
     * Planets and points standing in one sign.
     *
     * @param  array<string, mixed>  $chart
     */
    public function signContent(array $chart, string $sign, string $locale): string
    {
        $wanted = $this->signIndex($sign);
        if ($wanted === null) {
            return '—';
        }

        $parts = [];
        foreach ($this->rows($chart) as $row) {
            if ($this->signIndex((string) ($row['sign'] ?? '')) !== $wanted) {
                continue;
            }
            $parts[] = $this->occupant($row, $locale);
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array{position: string, relations: string}
     */
    public function bodySlice(array $chart, string $body, string $locale): array
    {
        $rows = $this->rows($chart);
        $index = null;
        foreach ($rows as $i => $row) {
            if ((string) $row['name'] === $body) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return ['position' => '—', 'relations' => '—'];
        }

        $parts = $this->aspectParts($rows, $index, $locale, true);

        return [
            'position' => $this->place($rows[$index], $locale, true),
            'relations' => $parts === [] ? '—' : implode('; ', $parts),
        ];
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return list<array<string, mixed>>
     */
    private function rows(array $chart): array
    {
        $byName = [];
        foreach ($chart['objects'] ?? [] as $object) {
            if (! is_array($object)) {
                continue;
            }
            $name = (string) ($object['name'] ?? '');
            if ($name !== '' && in_array($name, self::ORDER, true)) {
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

        $rows = [];
        foreach (self::ORDER as $name) {
            if (isset($byName[$name]) && is_array($byName[$name])) {
                $rows[] = $byName[$name];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function aspectParts(array $rows, int $index, string $locale, bool $all): array
    {
        $longitude = $rows[$index]['longitude'] ?? null;
        if (! is_numeric($longitude)) {
            return [];
        }

        $parts = [];
        foreach ($rows as $otherIndex => $other) {
            if ($otherIndex === $index) {
                continue;
            }
            if (! $all && $otherIndex < $index) {
                continue;
            }
            $otherLon = $other['longitude'] ?? null;
            if (! is_numeric($otherLon)) {
                continue;
            }
            $angle = $this->aspect((float) $longitude, (float) $otherLon);
            if ($angle === null) {
                continue;
            }
            $parts[] = [$all ? $otherIndex : $otherIndex, $angle.'fok '.$this->bodyName((string) ($other['name'] ?? ''), $locale)];
        }

        usort($parts, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(fn (array $part): string => $part[1], $parts);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function place(array $row, string $locale, bool $withMotion): string
    {
        $sign = $this->signLabel((string) ($row['sign'] ?? ''), $row, $locale);
        $degree = $this->degreeOf($row);
        $bits = [$sign.' '.$this->fok($degree, $locale)];
        $house = is_numeric($row['house'] ?? null) ? (int) $row['house'] : 0;
        if ($house >= 1 && $house <= 12) {
            $bits[] = $this->houseLabel($house, $locale);
        }
        if ($withMotion && ! in_array((string) ($row['name'] ?? ''), self::POINTS, true)) {
            $bits[] = ! empty($row['retrograde']) ? 'R' : 'N';
        }

        return implode(', ', $bits);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function occupant(array $row, string $locale): string
    {
        $name = $this->bodyName((string) ($row['name'] ?? ''), $locale);
        $degree = $this->fok($this->degreeOf($row), $locale);
        if (in_array((string) ($row['name'] ?? ''), self::POINTS, true)) {
            return $name.', '.$degree;
        }

        $motion = ! empty($row['retrograde']) ? 'R' : 'N';

        return $name.', '.$motion.', '.$degree;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function degreeOf(array $row): float
    {
        if (is_numeric($row['degree'] ?? null)) {
            return (float) $row['degree'];
        }
        if (is_numeric($row['longitude'] ?? null)) {
            return $this->signDegree((float) $row['longitude']);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function signLabel(string $sign, array $row, string $locale): string
    {
        $index = $this->signIndex($sign);
        if ($index === null && is_numeric($row['longitude'] ?? null)) {
            $index = ((int) floor($this->norm((float) $row['longitude']) / 30.0)) % 12;
        }
        if ($index === null) {
            return $sign !== '' ? $sign : '—';
        }

        return ($locale === 'en' ? self::SIGNS_EN : self::SIGNS_HU)[$index];
    }

    private function signIndex(string $sign): ?int
    {
        $sign = trim($sign);
        if ($sign === '') {
            return null;
        }
        $en = array_search($sign, self::SIGNS_EN, true);
        if ($en !== false) {
            return (int) $en;
        }
        $hu = array_search($sign, self::SIGNS_HU, true);

        return $hu === false ? null : (int) $hu;
    }

    private function bodyName(string $name, string $locale): string
    {
        if ($locale === 'en') {
            return $name;
        }

        return self::BODY_HU[$name] ?? $name;
    }

    private function fok(float $degree, string $locale): string
    {
        $rounded = round($degree, 1);
        $text = abs($rounded - round($rounded)) < 0.05
            ? (string) (int) round($rounded)
            : number_format($rounded, 1, '.', '');

        return $text.($locale === 'en' ? '°' : 'fok');
    }

    private function houseLabel(int $house, string $locale): string
    {
        if ($locale !== 'en') {
            return $house.'.ház';
        }

        $mod = $house % 100;
        $suffix = match (true) {
            $mod >= 11 && $mod <= 13 => 'th',
            $house % 10 === 1 => 'st',
            $house % 10 === 2 => 'nd',
            $house % 10 === 3 => 'rd',
            default => 'th',
        };

        return $house.$suffix.' house';
    }

    private function aspect(float $a, float $b): ?int
    {
        $distance = $this->distance($a, $b);
        foreach (self::ASPECTS as [$angle, $nominal, $orb]) {
            if (abs($distance - $nominal) <= $orb) {
                return $angle;
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
     * @return array{name: string, longitude: float, sign: string, degree: float, house: int}
     */
    private function point(string $name, float $longitude, ?int $house): array
    {
        return [
            'name' => $name,
            'longitude' => $longitude,
            'sign' => self::SIGNS_EN[(int) floor($longitude / 30.0) % 12],
            'degree' => $this->signDegree($longitude),
            'house' => $house ?? 0,
        ];
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
