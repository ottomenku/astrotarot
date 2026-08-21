#!/usr/bin/env bash
set -euo pipefail
cd /var/www/tarot
git pull origin master
php artisan migrate --force
php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan tinker --execute='$n = \App\Models\User::query()->where("credits", 0)->update(["credits" => 10]); echo "granted_to=$n\n"; echo "users=".\App\Models\User::query()->count()."\n"; echo "with_10plus=".\App\Models\User::query()->where("credits", ">=", 10)->count()."\n";'
echo "deploy_ok $(git rev-parse --short HEAD)"
