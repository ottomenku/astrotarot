<?php

declare(strict_types=1);

/**
 * A már kiszámított asztrológiai eredményből determinisztikus pozitív/negatív
 * értéket rendel a tarot-kirakás minden helyéhez.
 *
 * Minden hely önálló besorolást kap, ezért három lap lehet akár mind pozitív
 * vagy mind negatív is. A hosszú távú arányt egy kicsi pozitív eltolás tartja
 * az 50–50 közelében, enyhén a pozitív oldal felé.
 */
final class TarotPositionPolarity
{
    private const DEFAULT_POSITIONS = [
        [
            'key' => 'helyzet',
            'name' => 'A jelenlegi helyzet lényege',
            'focus' => ['nap' => 0.35, 'hold' => 0.35, 'asc' => 0.30],
        ],
        [
            'key' => 'hato_ero',
            'name' => 'A ható erő vagy akadály',
            'focus' => ['szaturnusz' => 0.35, 'mars' => 0.30, 'pluto' => 0.20, 'uranusz' => 0.15],
        ],
        [
            'key' => 'tanacs',
            'name' => 'Tanács és várható irány',
            'focus' => ['jupiter' => 0.35, 'venusz' => 0.30, 'nap' => 0.20, 'mc' => 0.15],
        ],
    ];

    /** További helyekhez ciklikusan használt profilok. */
    private const FALLBACK_FOCUS = [
        ['merkur' => 0.40, 'hold' => 0.30, 'asc' => 0.30],
        ['felszallo_holdcsomo' => 0.35, 'jupiter' => 0.35, 'mc' => 0.30],
        ['venusz' => 0.35, 'mars' => 0.35, 'hold' => 0.30],
    ];

    private float $positiveBias;

    public function __construct(float $positiveBias = 0.02)
    {
        if ($positiveBias < 0.0 || $positiveBias > 0.10) {
            throw new InvalidArgumentException('A pozitív eltolás 0 és 0,10 közé essen.');
        }
        $this->positiveBias = $positiveBias;
    }

    /**
     * @param array<string, mixed> $astrologyResult Az AstroScoringEngine::analyze() eredménye.
     * @param null|array<int, array{key?: string, name?: string, focus?: array<string, float>}> $positions
     * @return array<string, mixed>
     */
    public function assign(array $astrologyResult, ?array $positions = null): array
    {
        $records = $astrologyResult['records'] ?? null;
        if (!is_array($records) || $records === []) {
            throw new InvalidArgumentException('Az asztrológiai eredmény records tömbje hiányzik vagy üres.');
        }

        $positionDefinitions = $this->preparePositions($positions ?? self::DEFAULT_POSITIONS);
        $objectMap = $this->buildObjectMap($records);
        $aspects = is_array($astrologyResult['aspects'] ?? null) ? $astrologyResult['aspects'] : [];
        $globalSignal = $this->globalSignal($astrologyResult, $records);
        $chartPhase = $this->chartPhase($records);

        $evaluated = [];
        foreach ($positionDefinitions as $index => $position) {
            $focusSignal = $this->focusSignal($position['focus'], $objectMap, $globalSignal);
            $aspectSignal = $this->aspectSignal($position['focus'], $aspects, $globalSignal);

            // A fázis az objektumok tényleges hosszúságából származó,
            // szimmetrikus összetevő; hosszú távon segíti az 50–50 közeli arányt.
            $phaseSignal = sin(deg2rad($chartPhase + ($index * (360.0 / count($positionDefinitions)))));

            $raw =
                (0.35 * $globalSignal) +
                (0.30 * $focusSignal) +
                (0.20 * $aspectSignal) +
                (0.15 * $phaseSignal) +
                $this->positiveBias;

            $evaluated[] = [
                'index' => $index,
                'key' => $position['key'],
                'name' => $position['name'],
                'focus' => $position['focus'],
                'global_signal' => $globalSignal,
                'focus_signal' => $focusSignal,
                'aspect_signal' => $aspectSignal,
                'phase_signal' => $phaseSignal,
                'raw' => $this->clamp($raw, -1.0, 1.0),
            ];
        }

        $output = [];
        $positiveCount = 0;
        $negativeCount = 0;
        foreach ($evaluated as $item) {
            $isPositive = $item['raw'] >= 0.0;
            $isPositive ? $positiveCount++ : $negativeCount++;
            $strength = max(1, min(100, (int) round(abs($item['raw']) * 100)));
            $signedValue = $isPositive ? $strength : -$strength;

            $focusNames = implode(', ', array_map(
                fn(string $object): string => $this->displayObject($object),
                array_keys($item['focus'])
            ));

            $output[] = [
                'hely' => $item['index'] + 1,
                'kulcs' => $item['key'],
                'nev' => $item['name'],
                'besorolas' => $isPositive ? 'pozitív' : 'negatív',
                'ertek' => $signedValue,
                'erosseg' => $strength,
                'nyers_asztrologiai_ertek' => (int) round($item['raw'] * 100),
                'reszertekek' => [
                    'teljes_keplet' => (int) round($item['global_signal'] * 100),
                    'kiemelt_objektumok' => (int) round($item['focus_signal'] * 100),
                    'fenyszogek' => (int) round($item['aspect_signal'] * 100),
                    'ekliptikai_fazis' => (int) round($item['phase_signal'] * 100),
                    'pozitiv_eltolas' => (int) round($this->positiveBias * 100),
                ],
                'magyarazat' => sprintf(
                    'A(z) %d. hely fókuszobjektumai: %s. Teljes képlet: %+d, fókusz: %+d, fényszögek: %+d, ekliptikai fázis: %+d, pozitív eltolás: +%d. A hely önálló nyers értéke %s nulla, ezért a besorolása %s; más helyek eredménye ezt nem írja felül.',
                    $item['index'] + 1,
                    $focusNames,
                    (int) round($item['global_signal'] * 100),
                    (int) round($item['focus_signal'] * 100),
                    (int) round($item['aspect_signal'] * 100),
                    (int) round($item['phase_signal'] * 100),
                    (int) round($this->positiveBias * 100),
                    $item['raw'] >= 0.0 ? 'eléri vagy meghaladja' : 'nem éri el',
                    $isPositive ? 'pozitív' : 'negatív'
                ),
            ];
        }

        return [
            'positions' => $output,
            'summary' => [
                'pozitiv_helyek' => $positiveCount,
                'negativ_helyek' => $negativeCount,
                'arany' => sprintf('%d:%d', $positiveCount, $negativeCount),
                'teljes_keplet_jelzese' => (int) round($globalSignal * 100),
                'pozitiv_eltolas' => (int) round($this->positiveBias * 100),
                'celzott_hosszu_tavu_arany' => 'kb. 52–55% pozitív',
                'keplet_azonosito' => $this->chartFingerprint($records),
                'szabaly' => 'Minden helyet önállóan, nulla küszöbnél osztályozunk. Nincs kikényszerített darabszám: három helyből 0, 1, 2 vagy 3 is lehet pozitív. A +2 százalékpontos eltolás csak a sok képleten mért eloszlást billenti enyhén pozitív irányba.',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $positions
     * @return array<int, array{key: string, name: string, focus: array<string, float>}>
     */
    private function preparePositions(array $positions): array
    {
        if ($positions === []) {
            throw new InvalidArgumentException('Legalább egy tarot-hely szükséges.');
        }

        $result = [];
        foreach (array_values($positions) as $index => $position) {
            if (!is_array($position)) {
                throw new InvalidArgumentException('Minden tarot-helynek tömbnek kell lennie.');
            }
            $focus = $position['focus'] ?? self::FALLBACK_FOCUS[$index % count(self::FALLBACK_FOCUS)];
            if (!is_array($focus) || $focus === []) {
                throw new InvalidArgumentException('A tarot-hely focus tömbje nem lehet üres.');
            }

            $normalizedFocus = [];
            foreach ($focus as $object => $weight) {
                if (!is_numeric($weight) || (float) $weight <= 0.0) {
                    throw new InvalidArgumentException('A fókuszsúlyoknak pozitív számoknak kell lenniük.');
                }
                $normalizedFocus[$this->normalizeObject((string) $object)] = (float) $weight;
            }

            $result[] = [
                'key' => (string) ($position['key'] ?? 'hely_' . ($index + 1)),
                'name' => (string) ($position['name'] ?? ($index + 1) . '. tarot-hely'),
                'focus' => $normalizedFocus,
            ];
        }
        return $result;
    }

    /** @param array<int, array<string, mixed>> $records */
    private function buildObjectMap(array $records): array
    {
        $map = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $name = (string) ($record['normalizalt_objektum'] ?? $record['objektum'] ?? $record['object'] ?? '');
            if ($name === '') {
                continue;
            }
            $key = $this->normalizeObject($name);
            $positive = (float) ($record['pozitiv_pont'] ?? 0.0);
            $negative = (float) ($record['negativ_pont'] ?? 0.0);
            $map[$key] = [
                'signal' => $this->normalizedDifference($positive, $negative),
                'positive' => $positive,
                'negative' => $negative,
                'longitude' => (float) ($record['ekliptikai_hosszusag'] ?? 0.0),
            ];
        }
        return $map;
    }

    /** @param array<string, float> $focus */
    private function focusSignal(array $focus, array $objectMap, float $fallback): float
    {
        $sum = 0.0;
        $weightSum = 0.0;
        foreach ($focus as $object => $weight) {
            if (!isset($objectMap[$object])) {
                continue;
            }
            $sum += $objectMap[$object]['signal'] * $weight;
            $weightSum += $weight;
        }
        return $weightSum > 0.0 ? $this->clamp($sum / $weightSum, -1.0, 1.0) : $fallback;
    }

    /** @param array<string, float> $focus @param array<int, array<string, mixed>> $aspects */
    private function aspectSignal(array $focus, array $aspects, float $fallback): float
    {
        $sum = 0.0;
        $weightSum = 0.0;
        foreach ($aspects as $aspect) {
            if (!is_array($aspect)) {
                continue;
            }
            $objectA = $this->normalizeObject((string) ($aspect['objektum_1'] ?? ''));
            $objectB = $this->normalizeObject((string) ($aspect['objektum_2'] ?? ''));
            if (!isset($focus[$objectA]) && !isset($focus[$objectB])) {
                continue;
            }

            $strength = $this->clamp((float) ($aspect['erosseg'] ?? 0.0), 0.0, 1.0);
            $tone = $this->normalizeText((string) ($aspect['jelleg'] ?? ''));
            $direction = $tone === 'pozitiv' ? 1.0 : ($tone === 'negativ' ? -1.0 : 0.0);
            $profileWeight = max($focus[$objectA] ?? 0.0, $focus[$objectB] ?? 0.0);
            $effectiveWeight = max(0.1, $profileWeight) * max(0.05, $strength);
            $sum += $direction * $effectiveWeight;
            $weightSum += $effectiveWeight;
        }
        return $weightSum > 0.0 ? $this->clamp($sum / $weightSum, -1.0, 1.0) : $fallback;
    }

    /** @param array<string, mixed> $result @param array<int, array<string, mixed>> $records */
    private function globalSignal(array $result, array $records): float
    {
        $tone = $result['summary']['pozitiv_negativ'] ?? null;
        if (is_array($tone)) {
            $positive = (float) ($tone['nyers_pozitiv'] ?? $tone['pozitiv'] ?? 0.0);
            $negative = (float) ($tone['nyers_negativ'] ?? $tone['negativ'] ?? 0.0);
            if (($positive + $negative) > 0.0) {
                return $this->normalizedDifference($positive, $negative);
            }
        }

        $positive = 0.0;
        $negative = 0.0;
        foreach ($records as $record) {
            if (is_array($record)) {
                $positive += (float) ($record['pozitiv_pont'] ?? 0.0);
                $negative += (float) ($record['negativ_pont'] ?? 0.0);
            }
        }
        return $this->normalizedDifference($positive, $negative);
    }

    /** @param array<int, array<string, mixed>> $records */
    private function chartPhase(array $records): float
    {
        $x = 0.0;
        $y = 0.0;
        $weightSum = 0.0;
        foreach ($records as $record) {
            if (!is_array($record) || !isset($record['ekliptikai_hosszusag'])) {
                continue;
            }
            $longitude = (float) $record['ekliptikai_hosszusag'];
            $weight = max(0.5, (float) ($record['elem_ertek'] ?? 1.0));
            $x += cos(deg2rad($longitude)) * $weight;
            $y += sin(deg2rad($longitude)) * $weight;
            $weightSum += $weight;
        }
        if ($weightSum === 0.0 || (abs($x) < 0.000001 && abs($y) < 0.000001)) {
            return 0.0;
        }
        $angle = rad2deg(atan2($y, $x));
        return $angle < 0.0 ? $angle + 360.0 : $angle;
    }

    private function normalizedDifference(float $positive, float $negative): float
    {
        return $this->clamp(($positive - $negative) / ($positive + $negative + 1.0), -1.0, 1.0);
    }

    /** @param array<int, array<string, mixed>> $records */
    private function chartFingerprint(array $records): string
    {
        $canonical = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $canonical[] = [
                'object' => $this->normalizeObject((string) ($record['normalizalt_objektum'] ?? $record['objektum'] ?? $record['object'] ?? '')),
                'longitude' => round((float) ($record['ekliptikai_hosszusag'] ?? 0.0), 6),
                'retrograde' => (bool) ($record['retrograd'] ?? $record['retrograde'] ?? false),
            ];
        }
        usort($canonical, static fn(array $a, array $b): int => $a['object'] <=> $b['object']);
        return substr(hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 16);
    }

    private function normalizeObject(string $value): string
    {
        $normalized = $this->normalizeText($value);
        return [
            'sun'=>'nap', 'moon'=>'hold', 'mercury'=>'merkur', 'venus'=>'venusz',
            'saturn'=>'szaturnusz', 'uranus'=>'uranusz', 'neptune'=>'neptunusz',
            'ascendant'=>'asc', 'aszcendens'=>'asc', 'midheaven'=>'mc', 'medium_coeli'=>'mc',
            'felszallo_holdcsomo'=>'felszallo_holdcsomo', 'eszaki_holdcsomo'=>'felszallo_holdcsomo',
            'north_node'=>'felszallo_holdcsomo', 'leszallo_holdcsomo'=>'leszallo_holdcsomo',
            'deli_holdcsomo'=>'leszallo_holdcsomo', 'south_node'=>'leszallo_holdcsomo',
        ][$normalized] ?? $normalized;
    }

    private function normalizeText(string $value): string
    {
        $value = strtr(trim($value), [
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ö'=>'O','Ő'=>'O','Ú'=>'U','Ü'=>'U','Ű'=>'U',
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u',
        ]);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', strtolower($value));
        return trim((string) $normalized, '_');
    }

    private function displayObject(string $object): string
    {
        return [
            'nap'=>'Nap', 'hold'=>'Hold', 'merkur'=>'Merkúr', 'venusz'=>'Vénusz',
            'mars'=>'Mars', 'jupiter'=>'Jupiter', 'szaturnusz'=>'Szaturnusz',
            'uranusz'=>'Uránusz', 'neptunusz'=>'Neptunusz', 'pluto'=>'Plútó',
            'asc'=>'ASC', 'mc'=>'MC', 'felszallo_holdcsomo'=>'Felszálló Holdcsomó',
        ][$object] ?? $object;
    }

    private function clamp(float $value, float $minimum, float $maximum): float
    {
        return max($minimum, min($maximum, $value));
    }
}
