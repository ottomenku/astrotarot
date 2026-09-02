<?php

$root = realpath(__DIR__.'/../../public/card-pack');
if ($root === false) {
    fwrite(STDERR, "card-pack root missing\n");
    exit(1);
}

foreach (['cosplay-orakulum', 'cosplay-tarot'] as $slug) {
    foreach (['figures', 'card-back'] as $sub) {
        $dir = $root.DIRECTORY_SEPARATOR.$slug.DIRECTORY_SEPARATOR.$sub;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

$oraclePath = $root.DIRECTORY_SEPARATOR.'egi-orakulum'.DIRECTORY_SEPARATOR.'deck.json';
$raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($oraclePath)) ?? '';
$oracle = json_decode($raw, true);
if (! is_array($oracle) || ! isset($oracle['figures'])) {
    fwrite(STDERR, "failed to read {$oraclePath} (".strlen($raw).' bytes, '.json_last_error_msg().")\n");
    exit(1);
}
$oracle['id'] = 'cosplay-orakulum';
$oracle['name'] = ['hu' => 'Cosplay orákulum', 'en' => 'Cosplay oracle'];
$oracle['style'] = 'photoreal-cosplay';
$oracle['polarFigures'] = false;
$oracle['totalCards'] = count($oracle['figures']);
unset($oracle['backgrounds']);
$oracle['note'] = 'Full-bleed photorealistic sexy adult cosplay. One figure image per card, no light/shadow variants, no separate background.';
file_put_contents(
    $root.DIRECTORY_SEPARATOR.'cosplay-orakulum'.DIRECTORY_SEPARATOR.'deck.json',
    json_encode($oracle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
);

$tarot = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($root.DIRECTORY_SEPARATOR.'tarot'.DIRECTORY_SEPARATOR.'deck.json')) ?? '', true);
$majors = array_values(array_filter($tarot['figures'], fn ($figure) => ($figure['kind'] ?? '') === 'major'));
$tarot['id'] = 'cosplay-tarot';
$tarot['name'] = ['hu' => 'Cosplay tarot', 'en' => 'Cosplay tarot'];
$tarot['style'] = 'photoreal-cosplay';
$tarot['polarFigures'] = false;
$tarot['figures'] = $majors;
$tarot['totalCards'] = count($majors);
$tarot['composition'] = ['majors' => count($majors)];
unset($tarot['suits'], $tarot['ranks'], $tarot['backgrounds']);
$tarot['note'] = '22 major arcana as photorealistic sexy adult cosplay. One figure image per card, no light/shadow variants, no separate background.';
file_put_contents(
    $root.DIRECTORY_SEPARATOR.'cosplay-tarot'.DIRECTORY_SEPARATOR.'deck.json',
    json_encode($tarot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
);

echo 'oracle '.count($oracle['figures']).PHP_EOL;
echo 'tarot '.count($majors).PHP_EOL;
