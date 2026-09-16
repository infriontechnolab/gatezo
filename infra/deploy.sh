#!/usr/bin/env bash
# Zero-frills deploy. Run on the server as the deploy user:
#   bash /var/www/eventqr/infra/deploy.sh
# Or from GitHub Actions over SSH. Assumes: repo cloned at /var/www/eventqr,
# .env in place, nginx + php8.4-fpm + mysql + node installed.
set -euo pipefail
cd /var/www/eventqr

php artisan down --retry=10 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan optimize          # config + route + view + event caches
php artisan filament:optimize # Filament component + icon caches
php artisan storage:link >/dev/null 2>&1 || true
sudo supervisorctl restart eventqr-queue >/dev/null 2>&1 || true
php artisan up
echo "deployed $(git rev-parse --short HEAD)"
