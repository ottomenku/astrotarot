<?php

declare(strict_types=1);

require __DIR__ . '/AstroScoringEngine.php';
require __DIR__ . '/TarotPositionPolarity.php';

function assertAlmost(float $actual, float $expected, string $message): void
{
    if (abs($actual - $expected) > 0.0001) {
        throw new RuntimeException("{$message}: várt {$expected}, kapott {$actual}");
    }
}

// Fényszög nélküli alapképletek: a példákban szereplő 1 / 2 / 3 pont.
$engine = new AstroScoringEngine(0.5);
$single = static fn(array $record): array => $engine->analyze([$record])['records'][0];

$generic = $single(['objektum' => 'Ceres', 'jegy' => 'Kos', 'fok' => 17.0]);
assertAlmost((float) $generic['elem_ertek'], 1.0, 'Általános objektum tüzes jegyben');

$mars = $single(['objektum' => 'Mars', 'jegy' => 'Nyilas', 'fok' => 17.0]);
assertAlmost((float) $mars['elem_ertek'], 2.0, 'Tüzes bolygó tüzes jegyben');

$sun = $single(['objektum' => 'Nap', 'jegy' => 'Nyilas', 'fok' => 17.0]);
assertAlmost((float) $sun['elem_ertek'], 3.0, 'Nap tüzes jegyben');

$retroMars = $single(['objektum' => 'Mars', 'jegy' => 'Nyilas', 'fok' => 17.0, 'retrograd' => true]);
assertAlmost((float) $retroMars['elem_ertek'], 1.0, 'Retrográd Mars 50%-os súllyal');

// Pontos trigon felismerése.
$aspectResult = $engine->analyze([
    ['objektum' => 'Nap', 'jegy' => 'Kos', 'fok' => 10.0],
    ['objektum' => 'Jupiter', 'jegy' => 'Oroszlán', 'fok' => 10.0],
]);
if (($aspectResult['aspects'][0]['fenyszog'] ?? null) !== 'trigon') {
    throw new RuntimeException('A pontos trigon felismerése sikertelen.');
}
if (($aspectResult['summary']['pozitiv_negativ']['nev'] ?? null) !== 'pozitív') {
    throw new RuntimeException('A támogató összesítésnek pozitívnak kell lennie.');
}

// A tarot-helyek determinisztikus, egymástól független besorolása.
$positionEngine = new TarotPositionPolarity();
$positionResultA = $positionEngine->assign($aspectResult);
$positionResultB = $positionEngine->assign($aspectResult);

if ($positionResultA !== $positionResultB) {
    throw new RuntimeException('Ugyanarra a képletre ugyanazokat a tarot-hely értékeket kell kapni.');
}

foreach ($positionResultA['positions'] as $position) {
    if ($position['ertek'] === 0) {
        throw new RuntimeException('A tarot-hely értéke nem lehet nulla.');
    }
    if ($position['besorolas'] === 'pozitív' && $position['ertek'] < 0) {
        throw new RuntimeException('A pozitív hely előjeles értéke hibás.');
    }
    if ($position['besorolas'] === 'negatív' && $position['ertek'] > 0) {
        throw new RuntimeException('A negatív hely előjeles értéke hibás.');
    }
}

// Szélsőséges képleteknél mindhárom hely kaphat azonos előjelet.
$mockObjects = ['Nap', 'Hold', 'ASC', 'Szaturnusz', 'Mars', 'Plútó', 'Uránusz', 'Jupiter', 'Vénusz', 'MC'];
$mockResult = static function (
    float $positive,
    float $negative,
    float $longitude = 0.0
) use ($mockObjects): array {
    $records = array_map(
        static fn(string $object): array => [
            'normalizalt_objektum' => $object,
            'ekliptikai_hosszusag' => $longitude,
            'elem_ertek' => 1.0,
            'pozitiv_pont' => $positive,
            'negativ_pont' => $negative,
        ],
        $mockObjects
    );

    return [
        'records' => $records,
        'aspects' => [],
        'summary' => [
            'pozitiv_negativ' => [
                'nyers_pozitiv' => $positive * count($mockObjects),
                'nyers_negativ' => $negative * count($mockObjects),
            ],
        ],
    ];
};

$allPositive = $positionEngine->assign($mockResult(10.0, 0.0));
$allPositiveCount = count(array_filter(
    $allPositive['positions'],
    static fn(array $position): bool => $position['besorolas'] === 'pozitív'
));
if ($allPositiveCount !== 3) {
    throw new RuntimeException('Erősen támogató képletnél mindhárom tarot-hely lehet pozitív.');
}

$allNegative = $positionEngine->assign($mockResult(0.0, 10.0));
$allNegativeCount = count(array_filter(
    $allNegative['positions'],
    static fn(array $position): bool => $position['besorolas'] === 'negatív'
));
if ($allNegativeCount !== 3) {
    throw new RuntimeException('Erősen terhelt képletnél mindhárom tarot-hely lehet negatív.');
}

// Semleges minták sokaságán az arány 50–50 közelében, enyhén pozitív marad.
$samplePositive = 0;
$sampleTotal = 0;
for ($longitude = 0; $longitude < 360; $longitude += 10) {
    $sample = $positionEngine->assign($mockResult(0.0, 0.0, (float) $longitude));
    $samplePositive += count(array_filter(
        $sample['positions'],
        static fn(array $position): bool => $position['besorolas'] === 'pozitív'
    ));
    $sampleTotal += count($sample['positions']);
}
$samplePositiveRate = $samplePositive / $sampleTotal;
if ($samplePositiveRate < 0.50 || $samplePositiveRate > 0.58) {
    throw new RuntimeException(sprintf(
        'A hosszú távú pozitív arány legyen 50–58%% között; kapott érték: %.2f%%.',
        $samplePositiveRate * 100
    ));
}

echo "Minden teszt sikeres.\n";
