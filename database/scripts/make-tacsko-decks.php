<?php

$root = realpath(__DIR__.'/../../public/card-pack');
if ($root === false) {
    fwrite(STDERR, "card-pack root missing\n");
    exit(1);
}

foreach (['tacsko-orakulum', 'tacsko-tarot'] as $slug) {
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
$oracle['id'] = 'tacsko-orakulum';
$oracle['name'] = ['hu' => 'Tacskó orákulum', 'en' => 'Dachshund oracle'];
$oracle['style'] = 'dachshund-fullbleed';
$oracle['polarFigures'] = false;
$oracle['totalCards'] = count($oracle['figures']);
unset($oracle['backgrounds']);
$oracle['note'] = 'Full-bleed dachshund cards. One figure image per card, no light/shadow variants, no separate background.';
file_put_contents(
    $root.DIRECTORY_SEPARATOR.'tacsko-orakulum'.DIRECTORY_SEPARATOR.'deck.json',
    json_encode($oracle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
);

$tarot = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($root.DIRECTORY_SEPARATOR.'tarot'.DIRECTORY_SEPARATOR.'deck.json')) ?? '', true);
$majors = array_values(array_filter($tarot['figures'], fn ($figure) => ($figure['kind'] ?? '') === 'major'));
$tarot['id'] = 'tacsko-tarot';
$tarot['name'] = ['hu' => 'Tacskó tarot', 'en' => 'Dachshund tarot'];
$tarot['style'] = 'dachshund-fullbleed';
$tarot['polarFigures'] = false;
$tarot['figures'] = $majors;
$tarot['totalCards'] = count($majors);
$tarot['composition'] = ['majors' => count($majors)];
unset($tarot['suits'], $tarot['ranks'], $tarot['backgrounds']);
$tarot['note'] = '22 major arcana as dachshund portraits. One figure image per card, no light/shadow variants, no separate background.';
file_put_contents(
    $root.DIRECTORY_SEPARATOR.'tacsko-tarot'.DIRECTORY_SEPARATOR.'deck.json',
    json_encode($tarot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
);

echo 'oracle '.count($oracle['figures']).PHP_EOL;
echo 'tarot '.count($majors).PHP_EOL;
