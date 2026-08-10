<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Konfigurálható, szabályalapú asztrológiai pontozó.
 *
 * Fontos: ez egy értelmezési modell, nem tudományos mérőeszköz. A jegyek
 * pozitív/negatív polaritását (aktív/befogadó) külön kezeli az esemény
 * támogató/feszült jellegétől.
 */
final class AstroScoringEngine
{
    private const SIGNS = [
        'kos'      => ['name' => 'Kos',      'element' => 'tűz',    'modality' => 'fő',       'polarity' => 'pozitív (aktív)',    'start' => 0],
        'bika'     => ['name' => 'Bika',     'element' => 'föld',   'modality' => 'szilárd',   'polarity' => 'negatív (befogadó)', 'start' => 30],
        'ikrek'    => ['name' => 'Ikrek',    'element' => 'levegő', 'modality' => 'változó',   'polarity' => 'pozitív (aktív)',    'start' => 60],
        'rak'      => ['name' => 'Rák',      'element' => 'víz',    'modality' => 'fő',       'polarity' => 'negatív (befogadó)', 'start' => 90],
        'oroszlan' => ['name' => 'Oroszlán', 'element' => 'tűz',    'modality' => 'szilárd',   'polarity' => 'pozitív (aktív)',    'start' => 120],
        'szuz'     => ['name' => 'Szűz',     'element' => 'föld',   'modality' => 'változó',   'polarity' => 'negatív (befogadó)', 'start' => 150],
        'merleg'   => ['name' => 'Mérleg',   'element' => 'levegő', 'modality' => 'fő',       'polarity' => 'pozitív (aktív)',    'start' => 180],
        'skorpio'  => ['name' => 'Skorpió',  'element' => 'víz',    'modality' => 'szilárd',   'polarity' => 'negatív (befogadó)', 'start' => 210],
        'nyilas'   => ['name' => 'Nyilas',   'element' => 'tűz',    'modality' => 'változó',   'polarity' => 'pozitív (aktív)',    'start' => 240],
        'bak'      => ['name' => 'Bak',      'element' => 'föld',   'modality' => 'fő',       'polarity' => 'negatív (befogadó)', 'start' => 270],
        'vizonto'  => ['name' => 'Vízöntő',  'element' => 'levegő', 'modality' => 'szilárd',   'polarity' => 'pozitív (aktív)',    'start' => 300],
        'halak'    => ['name' => 'Halak',    'element' => 'víz',    'modality' => 'változó',   'polarity' => 'negatív (befogadó)', 'start' => 330],
    ];

    private const SIGN_ALIASES = [
        'aries' => 'kos', 'taurus' => 'bika', 'gemini' => 'ikrek', 'cancer' => 'rak',
        'leo' => 'oroszlan', 'virgo' => 'szuz', 'libra' => 'merleg', 'scorpio' => 'skorpio',
        'sagittarius' => 'nyilas', 'capricorn' => 'bak', 'aquarius' => 'vizonto', 'pisces' => 'halak',
    ];

    private const OBJECT_ALIASES = [
        'sun' => 'nap', 'moon' => 'hold', 'mercury' => 'merkur', 'venus' => 'venusz',
        'mars' => 'mars', 'jupiter' => 'jupiter', 'saturn' => 'szaturnusz',
        'uranus' => 'uranusz', 'neptune' => 'neptunusz', 'pluto' => 'pluto',
        'ascendant' => 'asc', 'aszcendens' => 'asc', 'medium_coeli' => 'mc',
        'midheaven' => 'mc', 'eszaki_holdcsomo' => 'felszallo_holdcsomo',
        'eszakiholdcsomo' => 'felszallo_holdcsomo', 'felszalloholdcsomo' => 'felszallo_holdcsomo',
        'north_node' => 'felszallo_holdcsomo', 'northnode' => 'felszallo_holdcsomo',
        'deli_holdcsomo' => 'leszallo_holdcsomo', 'deliholdcsomo' => 'leszallo_holdcsomo',
        'leszalloholdcsomo' => 'leszallo_holdcsomo', 'south_node' => 'leszallo_holdcsomo',
        'southnode' => 'leszallo_holdcsomo', 'true_node' => 'felszallo_holdcsomo',
        'truenode' => 'felszallo_holdcsomo', 'black_moon_lilith' => 'lilith',
        'blackmoonlilith' => 'lilith', 'mean_lilith' => 'lilith', 'meanlilith' => 'lilith',
    ];

    /** A bolygó természetes eleme. Egyezéskor +1 pont jár. */
    private const NATURAL_ELEMENTS = [
        'nap' => 'tűz', 'hold' => 'víz', 'merkur' => 'levegő', 'venusz' => 'föld',
        'mars' => 'tűz', 'jupiter' => 'tűz', 'szaturnusz' => 'föld',
        'uranusz' => 'levegő', 'neptunusz' => 'víz', 'pluto' => 'víz',
        'chiron' => 'víz', 'lilith' => 'víz',
    ];

    /** Kiemelt objektumok plusz súlya. */
    private const IMPORTANCE_BONUS = [
        'nap' => 1.0, 'hold' => 1.0, 'asc' => 2.0, 'mc' => 1.5,
        'vertex' => 0.5, 'felszallo_holdcsomo' => 0.5, 'leszallo_holdcsomo' => 0.5,
    ];

    /** Alaptermészet: pozitív = támogató, negatív = nehezítő. */
    private const OBJECT_TONE = [
        'nap' => 0.7, 'hold' => 0.3, 'merkur' => 0.0, 'venusz' => 1.3,
        'mars' => -0.8, 'jupiter' => 1.5, 'szaturnusz' => -1.3,
        'uranusz' => -0.3, 'neptunusz' => 0.0, 'pluto' => -0.6,
        'chiron' => -0.3, 'lilith' => -0.3, 'felszallo_holdcsomo' => 0.2,
        'leszallo_holdcsomo' => -0.2, 'asc' => 0.0, 'mc' => 0.0, 'vertex' => 0.0,
    ];

    private const DOMICILE = [
        'nap' => ['oroszlan'], 'hold' => ['rak'], 'merkur' => ['ikrek', 'szuz'],
        'venusz' => ['bika', 'merleg'], 'mars' => ['kos', 'skorpio'],
        'jupiter' => ['nyilas', 'halak'], 'szaturnusz' => ['bak', 'vizonto'],
        'uranusz' => ['vizonto'], 'neptunusz' => ['halak'], 'pluto' => ['skorpio'],
    ];

    private const EXALTATION = [
        'nap' => ['kos'], 'hold' => ['bika'], 'merkur' => ['szuz'],
        'venusz' => ['halak'], 'mars' => ['bak'], 'jupiter' => ['rak'],
        'szaturnusz' => ['merleg'],
    ];

    private const DETRIMENT = [
        'nap' => ['vizonto'], 'hold' => ['bak'], 'merkur' => ['nyilas', 'halak'],
        'venusz' => ['kos', 'skorpio'], 'mars' => ['bika', 'merleg'],
        'jupiter' => ['ikrek', 'szuz'], 'szaturnusz' => ['rak', 'oroszlan'],
    ];

    private const FALL = [
        'nap' => ['merleg'], 'hold' => ['skorpio'], 'merkur' => ['halak'],
        'venusz' => ['szuz'], 'mars' => ['rak'], 'jupiter' => ['bak'],
        'szaturnusz' => ['kos'],
    ];

    /** Szög, orb, jelleg, pozitív/negatív pont, intenzitás. */
    private const ASPECTS = [
        ['name' => 'együttállás', 'angle' => 0.0,   'orb' => 8.0, 'tone' => 'context',  'value' => 0.8, 'emphasis' => 0.30],
        ['name' => 'szextil',     'angle' => 60.0,  'orb' => 4.0, 'tone' => 'positive', 'value' => 0.8, 'emphasis' => 0.18],
        ['name' => 'kvadrát',     'angle' => 90.0,  'orb' => 6.0, 'tone' => 'negative', 'value' => 1.0, 'emphasis' => 0.25],
        ['name' => 'trigon',      'angle' => 120.0, 'orb' => 6.0, 'tone' => 'positive', 'value' => 1.0, 'emphasis' => 0.22],
        ['name' => 'kvinkunx',    'angle' => 150.0, 'orb' => 3.0, 'tone' => 'negative', 'value' => 0.5, 'emphasis' => 0.14],
        ['name' => 'oppozíció',   'angle' => 180.0, 'orb' => 8.0, 'tone' => 'negative', 'value' => 1.1, 'emphasis' => 0.28],
    ];

    private float $retrogradeFactor;

    public function __construct(float $retrogradeFactor = 0.5)
    {
        if ($retrogradeFactor < 0.0 || $retrogradeFactor > 1.0) {
            throw new InvalidArgumentException('A retrográd szorzónak 0 és 1 közé kell esnie.');
        }
        $this->retrogradeFactor = $retrogradeFactor;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array{records: array<int, array<string, mixed>>, aspects: array<int, array<string, mixed>>, summary: array<string, mixed>, model: array<string, mixed>}
     */
    public function analyze(array $records): array
    {
        if ($records === []) {
            throw new InvalidArgumentException('Legalább egy objektumrekord szükséges.');
        }

        $work = [];
        foreach ($records as $index => $record) {
            $work[$index] = $this->prepareRecord($record, $index);
        }

        $aspects = $this->calculateAspects($work);

        $elementTotals = ['tűz' => 0.0, 'víz' => 0.0, 'föld' => 0.0, 'levegő' => 0.0];
        $modalityTotals = ['fő' => 0.0, 'szilárd' => 0.0, 'változó' => 0.0];
        $positiveTotal = 0.0;
        $negativeTotal = 0.0;
        $outputRecords = [];

        foreach ($work as $item) {
            $elementTotals[$item['element']] += $item['element_score'];
            $modalityTotals[$item['modality']] += $item['modality_score'];
            $positiveTotal += $item['positive_score'];
            $negativeTotal += $item['negative_score'];

            $difference = $item['positive_score'] - $item['negative_score'];
            $classification = abs($difference) < 0.15
                ? 'semleges'
                : ($difference > 0 ? 'pozitív' : 'negatív');

            $outputRecords[] = array_merge($item['original'], [
                'normalizalt_objektum' => $item['object_name'],
                'normalizalt_jegy' => $item['sign_name'],
                'ekliptikai_hosszusag' => round($item['longitude'], 3),
                'elem' => $item['element'],
                'elem_ertek' => round($item['element_score'], 2),
                'minoseg' => $item['modality'],
                'minoseg_ertek' => round($item['modality_score'], 2),
                'jegy_polaritas' => $item['sign_polarity'],
                'pozitiv_pont' => round($item['positive_score'], 2),
                'negativ_pont' => round($item['negative_score'], 2),
                'pozitiv_negativ_besorolas' => $classification,
                'pozitiv_negativ_ertek' => round(abs($difference), 2),
                'magyarazat' => implode(' ', $item['explanations']),
            ]);
        }

        $summary = [
            'elemek' => $this->roundScores($elementTotals),
            'elem_dominancia' => $this->dominance($elementTotals),
            'minosegek' => $this->roundScores($modalityTotals),
            'minoseg_dominancia' => $this->dominance($modalityTotals),
            'pozitiv_negativ' => $this->toneDominance($positiveTotal, $negativeTotal),
        ];

        return [
            'records' => $outputRecords,
            'aspects' => $aspects,
            'summary' => $summary,
            'summary_clean' => [
                'elemek' => $summary['elemek'],
                'elem_dominancia' => $this->cleanDominance($elementTotals, [
                    'tűz' => 1,
                    'víz' => 2,
                    'föld' => 3,
                    'levegő' => 4,
                ]),
                'minosegek' => $summary['minosegek'],
                'minoseg_dominancia' => $this->cleanDominance($modalityTotals, [
                    'fő' => 1,
                    'szilárd' => 2,
                    'változó' => 3,
                ]),
                'pozitiv_negativ' => $this->cleanToneDominance($positiveTotal, $negativeTotal),
            ],
            'model' => [
                'retrograd_szorzo' => $this->retrogradeFactor,
                'alap_jegy_pont' => 1,
                'termeszetes_elem_egyezes_bonusz' => 1,
                'nap_hold_kiemeles' => 1,
                'asc_kiemeles' => 2,
                'megjegyzes' => 'A pozitív/negatív érték támogató–feszült jelleg, nem azonos a jegy aktív–befogadó polaritásával.',
            ],
        ];
    }

    /** @param array<string, mixed> $record */
    private function prepareRecord(array $record, int $index): array
    {
        $objectInput = $record['objektum'] ?? $record['object'] ?? null;
        $signInput = $record['jegy'] ?? $record['sign'] ?? null;
        $degreeInput = $record['fok'] ?? $record['degree'] ?? null;

        if (!is_string($objectInput) || trim($objectInput) === '') {
            throw new InvalidArgumentException("A(z) {$index}. rekord objektuma hiányzik.");
        }
        if (!is_string($signInput) || trim($signInput) === '') {
            throw new InvalidArgumentException("A(z) {$index}. rekord jegye hiányzik.");
        }
        if (!is_numeric($degreeInput)) {
            throw new InvalidArgumentException("A(z) {$index}. rekord foka nem szám.");
        }

        $degree = (float) $degreeInput;
        if ($degree < 0.0 || $degree >= 30.0) {
            throw new InvalidArgumentException("A(z) {$index}. rekord foka 0 és 30 közé essen (30 nélkül).");
        }

        $objectKey = $this->objectKey($objectInput);
        $signKey = $this->signKey($signInput);
        $sign = self::SIGNS[$signKey];
        $retrograde = (bool) ($record['retrograd'] ?? $record['retrograde'] ?? false);
        $factor = $retrograde ? $this->retrogradeFactor : 1.0;

        $score = 1.0;
        $explanations = [sprintf('%s a(z) %s jegy %.2f°-án: alap 1 pont a(z) %s elemnek és a(z) %s minőségnek.', $objectInput, $sign['name'], $degree, $sign['element'], $sign['modality'])];

        if ((self::NATURAL_ELEMENTS[$objectKey] ?? null) === $sign['element']) {
            $score += 1.0;
            $explanations[] = 'A bolygó természetes eleme egyezik a jegy elemével: +1 pont.';
        }

        $importance = self::IMPORTANCE_BONUS[$objectKey] ?? 0.0;
        if ($importance > 0.0) {
            $score += $importance;
            $explanations[] = sprintf('Kiemelt objektum súlya: +%s pont.', $this->formatNumber($importance));
        }

        $dignity = $this->dignity($objectKey, $signKey);
        if ($dignity['prominence'] > 0.0) {
            $score += $dignity['prominence'];
            $explanations[] = sprintf('%s: +%s hangsúlypont.', $dignity['label'], $this->formatNumber($dignity['prominence']));
        } elseif ($dignity['label'] !== null) {
            $explanations[] = $dignity['label'] . ' a pozitív/negatív mérlegben figyelembe véve.';
        }

        $tone = self::OBJECT_TONE[$objectKey] ?? 0.0;
        $positive = max(0.0, $tone * 0.5) + $dignity['positive'];
        $negative = max(0.0, -$tone * 0.5) + $dignity['negative'];

        if ($retrograde) {
            $score *= $factor;
            $positive *= $factor;
            $negative *= $factor;
            $explanations[] = sprintf('Retrográd: minden saját hozzájárulás %d%%-kal számít.', (int) round($factor * 100));
        }

        return [
            'index' => $index,
            'original' => $record,
            'object_key' => $objectKey,
            'object_name' => $this->displayObjectName($objectKey, $objectInput),
            'sign_key' => $signKey,
            'sign_name' => $sign['name'],
            'degree' => $degree,
            'longitude' => $sign['start'] + $degree,
            'element' => $sign['element'],
            'modality' => $sign['modality'],
            'sign_polarity' => $sign['polarity'],
            'retrograde' => $retrograde,
            'factor' => $factor,
            'element_score' => $score,
            'modality_score' => $score,
            'positive_score' => $positive,
            'negative_score' => $negative,
            'explanations' => $explanations,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $work
     * @return array<int, array<string, mixed>>
     */
    private function calculateAspects(array &$work): array
    {
        $output = [];
        $count = count($work);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $distance = $this->angularDistance($work[$i]['longitude'], $work[$j]['longitude']);
                $match = $this->matchAspect($distance, $work[$i]['object_key'], $work[$j]['object_key']);
                if ($match === null) {
                    continue;
                }

                $tone = $match['tone'];
                if ($tone === 'context') {
                    $pairTone = ((self::OBJECT_TONE[$work[$i]['object_key']] ?? 0.0) + (self::OBJECT_TONE[$work[$j]['object_key']] ?? 0.0)) / 2;
                    $tone = $pairTone > 0.15 ? 'positive' : ($pairTone < -0.15 ? 'negative' : 'mixed');
                }

                foreach ([$i, $j] as $participant) {
                    $factor = $work[$participant]['factor'];
                    $emphasis = $match['emphasis'] * $match['strength'] * $factor;
                    $work[$participant]['element_score'] += $emphasis;
                    $work[$participant]['modality_score'] += $emphasis;

                    $tonePoints = $match['value'] * $match['strength'] * $factor;
                    if ($tone === 'positive') {
                        $work[$participant]['positive_score'] += $tonePoints;
                    } elseif ($tone === 'negative') {
                        $work[$participant]['negative_score'] += $tonePoints;
                    } else {
                        $work[$participant]['positive_score'] += $tonePoints * 0.25;
                        $work[$participant]['negative_score'] += $tonePoints * 0.25;
                    }

                    $work[$participant]['explanations'][] = sprintf(
                        '%s %s-szal: %s jelleg, %.2f° orb, %.0f%% erősség.',
                        $match['name'],
                        $participant === $i ? $work[$j]['object_name'] : $work[$i]['object_name'],
                        $this->hungarianTone($tone),
                        $match['orb_distance'],
                        $match['strength'] * 100
                    );
                }

                $output[] = [
                    'objektum_1' => $work[$i]['object_name'],
                    'objektum_2' => $work[$j]['object_name'],
                    'fenyszog' => $match['name'],
                    'szog' => $match['angle'],
                    'orb' => round($match['orb_distance'], 2),
                    'erosseg' => round($match['strength'], 3),
                    'jelleg' => $this->hungarianTone($tone),
                ];
            }
        }

        return $output;
    }

    private function matchAspect(float $distance, string $objectA, string $objectB): ?array
    {
        $best = null;
        foreach (self::ASPECTS as $aspect) {
            $maxOrb = $aspect['orb'];
            if ($this->isLuminary($objectA) || $this->isLuminary($objectB)) {
                $maxOrb *= 1.20;
            }
            if ($this->isPoint($objectA) || $this->isPoint($objectB)) {
                $maxOrb *= 0.75;
            }

            $orbDistance = abs($distance - $aspect['angle']);
            if ($orbDistance > $maxOrb) {
                continue;
            }
            if ($best === null || $orbDistance < $best['orb_distance']) {
                $strength = max(0.05, 1.0 - ($orbDistance / $maxOrb));
                $best = array_merge($aspect, [
                    'orb_distance' => $orbDistance,
                    'strength' => $strength,
                    'effective_orb' => $maxOrb,
                ]);
            }
        }
        return $best;
    }

    /** @return array{label: ?string, prominence: float, positive: float, negative: float} */
    private function dignity(string $object, string $sign): array
    {
        $inDetriment = in_array($sign, self::DETRIMENT[$object] ?? [], true);
        $inFall = in_array($sign, self::FALL[$object] ?? [], true);
        if (in_array($sign, self::DOMICILE[$object] ?? [], true)) {
            return ['label' => 'uralmi helyzet', 'prominence' => 0.5, 'positive' => 0.8, 'negative' => 0.0];
        }
        if (in_array($sign, self::EXALTATION[$object] ?? [], true)) {
            return ['label' => 'emelt helyzet', 'prominence' => 0.4, 'positive' => 0.6, 'negative' => 0.0];
        }
        if ($inDetriment && $inFall) {
            return ['label' => 'rangvesztés és esés', 'prominence' => 0.0, 'positive' => 0.0, 'negative' => 1.2];
        }
        if ($inDetriment) {
            return ['label' => 'rangvesztés', 'prominence' => 0.0, 'positive' => 0.0, 'negative' => 0.8];
        }
        if ($inFall) {
            return ['label' => 'esés', 'prominence' => 0.0, 'positive' => 0.0, 'negative' => 0.7];
        }
        return ['label' => null, 'prominence' => 0.0, 'positive' => 0.0, 'negative' => 0.0];
    }

    private function signKey(string $value): string
    {
        $key = $this->normalize($value);
        $key = self::SIGN_ALIASES[$key] ?? $key;
        if (!isset(self::SIGNS[$key])) {
            throw new InvalidArgumentException("Ismeretlen jegy: {$value}");
        }
        return $key;
    }

    private function objectKey(string $value): string
    {
        $key = $this->normalize($value);
        return self::OBJECT_ALIASES[$key] ?? $key;
    }

    private function normalize(string $value): string
    {
        $value = strtr(trim($value), [
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ö'=>'O','Ő'=>'O','Ú'=>'U','Ü'=>'U','Ű'=>'U',
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u',
        ]);
        $value = strtolower($value);
        return preg_replace('/[^a-z0-9]+/', '_', $value) !== null
            ? trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_')
            : $value;
    }

    private function displayObjectName(string $key, string $fallback): string
    {
        return [
            'nap'=>'Nap', 'hold'=>'Hold', 'merkur'=>'Merkúr', 'venusz'=>'Vénusz', 'mars'=>'Mars',
            'jupiter'=>'Jupiter', 'szaturnusz'=>'Szaturnusz', 'uranusz'=>'Uránusz',
            'neptunusz'=>'Neptunusz', 'pluto'=>'Plútó', 'chiron'=>'Chiron', 'lilith'=>'Lilith',
            'asc'=>'ASC', 'mc'=>'MC', 'vertex'=>'Vertex',
            'felszallo_holdcsomo'=>'Felszálló Holdcsomó', 'leszallo_holdcsomo'=>'Leszálló Holdcsomó',
        ][$key] ?? $fallback;
    }

    private function isLuminary(string $object): bool
    {
        return in_array($object, ['nap', 'hold'], true);
    }

    private function isPoint(string $object): bool
    {
        return in_array($object, ['asc', 'mc', 'vertex', 'felszallo_holdcsomo', 'leszallo_holdcsomo', 'lilith'], true);
    }

    private function angularDistance(float $a, float $b): float
    {
        $difference = abs($a - $b);
        return min($difference, 360.0 - $difference);
    }

    /** @param array<string, float> $scores */
    private function dominance(array $scores): array
    {
        arsort($scores);
        $names = array_keys($scores);
        $values = array_values($scores);
        $balanced = isset($values[1]) && abs($values[0] - $values[1]) < 0.25;
        return [
            'nev' => $balanced ? "kiegyensúlyozott ({$names[0]}/{$names[1]})" : $names[0],
            'ertek' => (int) round($values[0]),
            'nyers_ertek' => round($values[0], 2),
        ];
    }

    /**
     * Egyértelmű győztes: döntetlen / közel azonos esetén a kisebb prioritásszám nyer.
     *
     * @param  array<string, float>  $scores
     * @param  array<string, int>  $priority
     * @return array{nev: string, ertek: int, nyers_ertek: float}
     */
    private function cleanDominance(array $scores, array $priority): array
    {
        $max = max($scores);
        $winner = null;
        $winnerPriority = PHP_INT_MAX;
        $winnerValue = $max;

        foreach ($scores as $name => $value) {
            if (abs($value - $max) >= 0.25) {
                continue;
            }

            $rank = $priority[$name] ?? PHP_INT_MAX;
            if ($rank < $winnerPriority) {
                $winner = $name;
                $winnerPriority = $rank;
                $winnerValue = $value;
            }
        }

        return [
            'nev' => (string) $winner,
            'ertek' => (int) round($winnerValue),
            'nyers_ertek' => round($winnerValue, 2),
        ];
    }

    private function toneDominance(float $positive, float $negative): array
    {
        $difference = $positive - $negative;
        $name = abs($difference) < 0.25 ? 'semleges' : ($difference > 0 ? 'pozitív' : 'negatív');
        return [
            'nev' => $name,
            'ertek' => (int) round(max($positive, $negative)),
            'netto_ertek' => (int) round($difference),
            'pozitiv' => (int) round($positive),
            'negativ' => (int) round($negative),
            'nyers_pozitiv' => round($positive, 2),
            'nyers_negativ' => round($negative, 2),
        ];
    }

    /** Clean: döntetlennél mindig pozitív. */
    private function cleanToneDominance(float $positive, float $negative): array
    {
        $difference = $positive - $negative;
        $name = $positive >= $negative ? 'pozitív' : 'negatív';

        return [
            'nev' => $name,
            'ertek' => (int) round(max($positive, $negative)),
            'netto_ertek' => (int) round($difference),
            'pozitiv' => (int) round($positive),
            'negativ' => (int) round($negative),
            'nyers_pozitiv' => round($positive, 2),
            'nyers_negativ' => round($negative, 2),
        ];
    }

    /** @param array<string, float> $scores */
    private function roundScores(array $scores): array
    {
        return array_map(static fn(float $value): int => (int) round($value), $scores);
    }

    private function hungarianTone(string $tone): string
    {
        return match ($tone) {
            'positive' => 'pozitív',
            'negative' => 'negatív',
            default => 'vegyes',
        };
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
