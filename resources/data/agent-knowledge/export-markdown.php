<?php

declare(strict_types=1);

$dir = __DIR__;
$out = $dir.'/upload';
if (! is_dir($out)) {
    mkdir($out);
}

$files = [
    '02-tarot-major-arcana.json',
    '03-tarot-minor-arcana.json',
    '04-astrology-framework.json',
    '05-astrology-planets.json',
    '06-astrology-signs.json',
    '07-astrology-houses.json',
    '08-astrology-aspects.json',
    '09-astrology-points.json',
    '10-astrology-fixed-stars.json',
];

foreach ($files as $file) {
    $data = json_decode((string) file_get_contents($dir.'/'.$file), true, 512, JSON_THROW_ON_ERROR);
    $lines = ['# '.($data['id'] ?? $file), ''];
    foreach ($data['records'] ?? [] as $record) {
        $title = $record['name_hu'] ?? $record['hu'] ?? $record['id'] ?? 'bejegyzes';
        $lines[] = '## '.$title;
        $lines[] = '';
        $lines[] = trim((string) ($record['text'] ?? ''));
        $lines[] = '';
    }
    $name = preg_replace('/\.json$/', '', $file).'.md';
    file_put_contents($out.'/'.$name, implode("\n", $lines));
    echo $name, ' ', strlen(implode("\n", $lines)), PHP_EOL;
}
