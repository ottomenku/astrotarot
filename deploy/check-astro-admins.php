<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "TRY_ASTRO_IMPORT:\n";
try {
    $admins = DB::select(
        'SELECT id, name, email, is_admin FROM vs_cline.users WHERE is_admin = 1 ORDER BY id'
    );
    echo 'FOUND='.count($admins)."\n";
    foreach ($admins as $a) {
        echo "{$a->id}|{$a->email}|{$a->name}\n";
    }
} catch (Throwable $e) {
    echo 'ERR='.$e->getMessage()."\n";
}

echo "DATABASES:\n";
try {
    foreach (DB::select('SHOW DATABASES') as $db) {
        $name = $db->Database ?? array_values((array) $db)[0];
        echo $name."\n";
    }
} catch (Throwable $e) {
    echo 'ERR='.$e->getMessage()."\n";
}
