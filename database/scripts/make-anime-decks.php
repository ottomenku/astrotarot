<?php

$root = realpath(__DIR__.'/../../public/card-pack');
if ($root === false) {
    fwrite(STDERR, "card-pack root missing\n");
    exit(1);
}

foreach (['anime-orakulum', 'anime-tarot'] as $slug) {
    foreach (['figures', 'card-back'] as $sub) {
        $dir = $root.DIRECTORY_SEPARATOR.$slug.DIRECTORY_SEPARATOR.$sub;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

$oraclePath = $root.DIRECTORY_SEPARATOR.'egi-orakulum'.DIRECTORY_SEPARATOR.'deck.json';
$raw = (string) file_get_contents($oraclePath);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
$oracle = json_decode($raw, true);
if (! is_array($oracle) || ! isset($oracle['figures'])) {
    fwrite(STDERR, "failed to read {$oraclePath} (".strlen($raw).' bytes, '.json_last_error_msg().")\n");
    exit(1);
}
$oracle['id'] = 'anime-orakulum';
$oracle['name'] = ['hu' => 'Anime orákulum', 'en' => 'Anime oracle'];
$oracle['style'] = 'anime-fullbleed';
$oracle['polarFigures'] = true;
$oracle['totalCards'] = count($oracle['figures']);
unset($oracle['backgrounds']);
$oracle['note'] = 'Full-bleed anime cards. Polarity is in the figure art (id-light.png / id-shadow.png), no separate background.';
file_put_contents(
    $root.DIRECTORY_SEPARATOR.'anime-orakulum'.DIRECTORY_SEPARATOR.'deck.json',
    json_encode($oracle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
);

$tarot = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($root.DIRECTORY_SEPARATOR.'tarot'.DIRECTORY_SEPARATOR.'deck.json')) ?? '', true);
$majors = array_values(array_filter($tarot['figures'], fn ($figure) => ($figure['kind'] ?? '') === 'major'));
$tarot['id'] = 'anime-tarot';
$tarot['name'] = ['hu' => 'Anime tarot', 'en' => 'Anime tarot'];
$tarot['style'] = 'anime-fullbleed';
$tarot['polarFigures'] = true;
$tarot['figures'] = $majors;
$tarot['totalCards'] = count($majors);
$tarot['composition'] = ['majors' => count($majors)];
unset($tarot['suits'], $tarot['ranks'], $tarot['backgrounds']);
$tarot['note'] = '22 major arcana, full-bleed anime. Polarity is in the figure art (id-light.png / id-shadow.png), no separate background.';
file_put_contents(
    $root.DIRECTORY_SEPARATOR.'anime-tarot'.DIRECTORY_SEPARATOR.'deck.json',
    json_encode($tarot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
);

echo 'oracle '.count($oracle['figures']).PHP_EOL;
echo 'tarot '.count($majors).PHP_EOL;
