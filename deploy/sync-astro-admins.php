#!/bin/bash
set -euo pipefail
cd /var/www/tarot

php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\Role;
use App\Models\Template;
use Illuminate\Support\Facades\DB;

$templateId = Template::query()->where("slug", "art-deco")->value("id")
    ?? Template::query()->value("id");

$admins = DB::select(
    "SELECT name, email, password, email_verified_at, remember_token
     FROM horoscope.users
     WHERE is_admin = 1
     ORDER BY id"
);

if ($admins === []) {
    fwrite(STDERR, "NO_ASTRO_ADMINS\n");
    exit(1);
}

foreach ($admins as $admin) {
    DB::table("users")->updateOrInsert(
        ["email" => $admin->email],
        [
            "name" => $admin->name ?: "Admin",
            "password" => $admin->password,
            "role" => Role::Admin->value,
            "template_id" => $templateId,
            "email_verified_at" => $admin->email_verified_at,
            "remember_token" => $admin->remember_token,
            "updated_at" => now(),
            "created_at" => now(),
        ]
    );
    echo "SYNCED ".$admin->email.PHP_EOL;
}

echo "DONE\n";
foreach (DB::table("users")->orderBy("id")->get(["id","email","role","name"]) as $u) {
    echo $u->id."|".$u->email."|".$u->role."|".$u->name.PHP_EOL;
}
'
