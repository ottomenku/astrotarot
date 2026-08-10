<?php

declare(strict_types=1);

require __DIR__ . '/AstroScoringEngine.php';
require __DIR__ . '/TarotPositionPolarity.php';

$records = [
    ['objektum' => 'Nap',       'jegy' => 'Rák',      'fok' => 27.0, 'retrograd' => false],
    ['objektum' => 'Hold',      'jegy' => 'Kos',      'fok' => 12.3, 'retrograd' => false],
    ['objektum' => 'Merkúr',    'jegy' => 'Oroszlán', 'fok' => 5.2,  'retrograd' => true],
    ['objektum' => 'Vénusz',    'jegy' => 'Ikrek',    'fok' => 28.0, 'retrograd' => false],
    ['objektum' => 'Mars',      'jegy' => 'Nyilas',   'fok' => 11.4, 'retrograd' => false],
    ['objektum' => 'Jupiter',   'jegy' => 'Rák',      'fok' => 9.8,  'retrograd' => false],
    ['objektum' => 'Szaturnusz','jegy' => 'Kos',      'fok' => 1.5,  'retrograd' => false],
    ['objektum' => 'ASC',       'jegy' => 'Mérleg',   'fok' => 18.0, 'retrograd' => false],
    ['objektum' => 'MC',        'jegy' => 'Rák',      'fok' => 20.1, 'retrograd' => false],
];

$engine = new AstroScoringEngine(retrogradeFactor: 0.5);
$result = $engine->analyze($records);
$result['tarot_helyek'] = (new TarotPositionPolarity(positiveBias: 0.02))->assign($result);

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
) . PHP_EOL;
