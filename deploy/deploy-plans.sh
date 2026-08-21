#!/usr/bin/env bash
set -euo pipefail
cd /var/www/tarot
git pull origin master
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan view:clear
php artisan config:clear
php artisan route:clear
echo "deploy_ok $(git rev-parse --short HEAD)"
