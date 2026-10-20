<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

echo "USERS:\n";
foreach (User::query()->orderBy('id')->get(['id', 'email', 'role', 'name']) as $u) {
    $role = $u->role instanceof BackedEnum ? $u->role->value : (string) $u->role;
    echo "{$u->id}|{$u->email}|{$role}|{$u->name}|isAdmin=".($u->isAdmin() ? '1' : '0')."\n";
}
echo 'COUNT='.User::query()->count()."\n";
echo 'APP_URL='.config('app.url')."\n";
echo 'SESSION_DOMAIN='.var_export(config('session.domain'), true)."\n";
echo 'SESSION_SECURE='.var_export(config('session.secure'), true)."\n";
echo 'SESSION_SAME_SITE='.var_export(config('session.same_site'), true)."\n";

foreach (User::query()->orderBy('id')->get() as $user) {
    $checks = [];
    foreach (['password', 'admin', 'Admin123!', '111111'] as $pwd) {
        if (Hash::check($pwd, $user->password)) {
            $checks[] = $pwd;
        }
    }
    echo 'PWD '.$user->email.': '.($checks === [] ? 'no_common' : implode(',', $checks))."\n";
}
