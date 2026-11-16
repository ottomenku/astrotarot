<?php

namespace App\Support;

/**
 * Name, life description and current-sky facts for one celestial helper.
 *
 * A planet line is position plus aspects. A sign line is the planets in it.
 * R is retrograde, N is direct.
 */
class CelestialHelperBrief
{
    /** @var list<string> */
    private const PLANETS = [
        'sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn',
        'uranus', 'neptune', 'pluto',
    ];

    /** @var list<string> */
    private const SIGNS = [
        'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo',
        'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces',
    ];

    /** @var array<string, string> */
    private const GLYPHS = [
        'sun' => '☉', 'moon' => '☽', 'mercury' => '☿', 'venus' => '♀', 'mars' => '♂',
        'jupiter' => '♃', 'saturn' => '♄', 'uranus' => '♅', 'neptune' => '♆', 'pluto' => '♇',
        'aries' => '♈', 'taurus' => '♉', 'gemini' => '♊', 'cancer' => '♋', 'leo' => '♌',
        'virgo' => '♍', 'libra' => '♎', 'scorpio' => '♏', 'sagittarius' => '♐',
        'capricorn' => '♑', 'aquarius' => '♒', 'pisces' => '♓',
    ];

    /** @var array<string, int> */
    private const ANGLES = [
        'együttállás' => 0, 'conjunction' => 0,
        'szextil' => 60, 'sextile' => 60,
        'kvadrát' => 90, 'square' => 90,
        'trigon' => 120, 'trine' => 120,
        'kvinkunx' => 150, 'quincunx' => 150,
        'oppozíció' => 180, 'opposition' => 180,
    ];

    /** @var array<string, string> */
    private const ROMAN = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $records = null;

    /**
     * @param  array{objects?: list<array<string, mixed>>, aspects?: list<array<string, mixed>>}|null  $chart
     * @return list<string>
     */
    public function lines(string $id, string $locale, string $polarity, ?array $chart, bool $withAstro = true): array
    {
        $locale = $locale === 'en' ? 'en' : 'hu';
        $title = $this->titled($id, $locale);
        $face = $polarity === 'shadow' ? 'shadow' : 'light';
        $lines = [
            $locale === 'en'
                ? 'celestial helper: '.$title.' ('.$id.'), polarity '.$face
                : 'égi segítő: '.$title.' ('.$id.'), polaritás '.$face,
        ];

        $description = $this->description($id, $locale);
        if ($description !== '') {
            $lines[] = ($locale === 'en' ? 'description: ' : 'leírás: ').$description;
        }

        if ($withAstro) {
            $astro = $this->astro($id, $locale, $chart);
            if ($astro !== '') {
                $lines[] = ($locale === 'en' ? 'astro: ' : 'asztro: ').$astro;
            }
        }

        return $lines;
    }

    /**
     * @param  array{objects?: list<array<string, mixed>>, aspects?: list<array<string, mixed>>}|null  $chart
     */
    public function astro(string $id, string $locale, ?array $chart): string
    {
        $locale = $locale === 'en' ? 'en' : 'hu';
        $title = $this->titled($id, $locale);
        if (in_array($id, self::SIGNS, true)) {
            return $title.': '.$this->signOccupants($id, $locale, $chart);
        }

        return $title.'; pos: '.$this->position($id, $locale, $chart).'; Rel: '.$this->relations($id, $locale, $chart);
    }

    private function description(string $id, string $locale): string
    {
        $record = $this->record($id);
        if ($record === null) {
            return '';
        }
        $key = $locale === 'en' ? 'life_en' : 'life_hu';

        return trim((string) ($record[$key] ?? ''));
    }

    /**
     * @param  array{objects?: list<array<string, mixed>>, aspects?: list<array<string, mixed>>}|null  $chart
     */
    private function position(string $id, string $locale, ?array $chart): string
    {
        $object = $this->object($id, $chart);
        if ($object === null) {
            return '—';
        }

        $signId = (string) ($object['sign'] ?? '');
        $sign = trim($this->glyph($signId).' '.$this->name($signId, $locale, (string) ($object['sign_label'] ?? '')));
        $motion = ! empty($object['retrograde']) ? 'R' : 'N';
        $parts = array_filter([
            $sign,
            $motion,
            $this->degree(is_numeric($object['degree'] ?? null) ? (float) $object['degree'] : 0.0),
            $this->house(isset($object['house']) ? (int) $object['house'] : 0, $locale),
        ], fn (string $part): bool => $part !== '');

        return implode(', ', $parts);
    }

    /**
     * @param  array{objects?: list<array<string, mixed>>, aspects?: list<array<string, mixed>>}|null  $chart
     */
    private function relations(string $id, string $locale, ?array $chart): string
    {
        $found = [];
        foreach (is_array($chart['aspects'] ?? null) ? $chart['aspects'] : [] as $aspect) {
            if (! is_array($aspect)) {
                continue;
            }
            $a = (string) ($aspect['a_id'] ?? '');
            $b = (string) ($aspect['b_id'] ?? '');
            $other = $a === $id ? $b : ($b === $id ? $a : '');
            $angle = self::ANGLES[(string) ($aspect['type'] ?? '')] ?? null;
            if ($other === '' || $other === $id || $angle === null) {
                continue;
            }
            $found[$other] = $angle;
        }

        if ($found === []) {
            return '—';
        }

        uksort($found, function (string $left, string $right): int {
            return $this->planetRank($left) <=> $this->planetRank($right);
        });

        $parts = [];
        foreach ($found as $other => $angle) {
            $parts[] = trim($this->glyph($other).' '.$this->name($other, $locale)).' '.$angle.'°';
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array{objects?: list<array<string, mixed>>, aspects?: list<array<string, mixed>>}|null  $chart
     */
    private function signOccupants(string $signId, string $locale, ?array $chart): string
    {
        $rows = [];
        foreach (is_array($chart['objects'] ?? null) ? $chart['objects'] : [] as $object) {
            if (! is_array($object)) {
                continue;
            }
            $id = (string) ($object['id'] ?? '');
            if ($id === '' || (string) ($object['sign'] ?? '') !== $signId) {
                continue;
            }
            if ((string) ($object['kind'] ?? '') !== 'planet' && ! in_array($id, self::PLANETS, true)) {
                continue;
            }
            $rows[$id] = $object;
        }

        if ($rows === []) {
            return '—';
        }

        uksort($rows, function (string $left, string $right): int {
            return $this->planetRank($left) <=> $this->planetRank($right);
        });

        $parts = [];
        foreach ($rows as $id => $object) {
            $motion = ! empty($object['retrograde']) ? 'R' : 'N';
            $degree = $this->degree(is_numeric($object['degree'] ?? null) ? (float) $object['degree'] : 0.0);
            $parts[] = trim($this->glyph($id).' '.$this->name($id, $locale)).', '.$motion.', '.$degree;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array{objects?: list<array<string, mixed>>, aspects?: list<array<string, mixed>>}|null  $chart
     * @return array<string, mixed>|null
     */
    private function object(string $id, ?array $chart): ?array
    {
        foreach (is_array($chart['objects'] ?? null) ? $chart['objects'] : [] as $object) {
            if (is_array($object) && (string) ($object['id'] ?? '') === $id) {
                return $object;
            }
        }

        return null;
    }

    private function titled(string $id, string $locale): string
    {
        return trim($this->glyph($id).' '.$this->name($id, $locale));
    }

    private function name(string $id, string $locale, string $fallback = ''): string
    {
        if (CelestialOracleCatalog::known($id)) {
            return CelestialOracleCatalog::label($id, $locale);
        }

        return $fallback !== '' ? $fallback : $id;
    }

    private function glyph(string $id): string
    {
        return self::GLYPHS[$id] ?? '';
    }

    private function degree(float $value): string
    {
        $rounded = round($value, 1);
        $text = abs($rounded - round($rounded)) < 0.05
            ? (string) (int) round($rounded)
            : number_format($rounded, 1, '.', '');

        return $text.'°';
    }

    private function house(int $house, string $locale): string
    {
        if ($house < 1 || $house > 12) {
            return '';
        }

        if ($locale === 'en') {
            $suffix = match ($house % 10) {
                1 => $house % 100 === 11 ? 'th' : 'st',
                2 => $house % 100 === 12 ? 'th' : 'nd',
                3 => $house % 100 === 13 ? 'th' : 'rd',
                default => 'th',
            };

            return $house.$suffix.' house';
        }

        return (self::ROMAN[$house] ?? (string) $house).'. ház';
    }

    private function planetRank(string $id): int
    {
        $index = array_search($id, self::PLANETS, true);

        return $index === false ? 100 : (int) $index;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function record(string $id): ?array
    {
        if (self::$records === null) {
            self::$records = [];
            foreach (['05-astrology-planets.json', '06-astrology-signs.json'] as $file) {
                $path = resource_path('data/agent-knowledge/'.$file);
                if (! is_file($path)) {
                    continue;
                }
                $decoded = json_decode((string) file_get_contents($path), true);
                foreach (is_array($decoded['records'] ?? null) ? $decoded['records'] : [] as $record) {
                    if (is_array($record) && isset($record['id'])) {
                        self::$records[(string) $record['id']] = $record;
                    }
                }
            }
        }

        return self::$records[$id] ?? null;
    }
}
