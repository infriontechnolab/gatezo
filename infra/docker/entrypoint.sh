#!/bin/sh
# Container start: wait for MySQL, migrate, warm caches, then hand over to supervisord.
# Config comes from environment variables (compose env_file), never a baked-in .env.
set -e
cd /var/www/gatezo

# One-off commands (`docker compose run --rm app php artisan ...`) skip the boot sequence.
if [ "$1" != "supervisord" ]; then
  exec "$@"
fi

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is empty. Generate one with: docker compose -f compose.prod.yml run --rm app php artisan key:generate --show" >&2
  exit 1
fi

echo "waiting for MySQL at ${DB_HOST:-db}:${DB_PORT:-3306}..."
i=0
until php -r 'new PDO("mysql:host=".getenv("DB_HOST")?:"db".";port=".(getenv("DB_PORT")?:3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
  i=$((i+1)); [ $i -gt 60 ] && { echo "MySQL not reachable after 60s" >&2; exit 1; }
  sleep 1
done

su-exec() { su -s /bin/sh www-data -c "$*"; }

su-exec php artisan storage:link --force >/dev/null 2>&1 || true
su-exec php artisan migrate --force --no-interaction
su-exec php artisan optimize --no-interaction
su-exec php artisan filament:optimize --no-interaction

if [ "$GATEZO_DEMO" = "true" ] && [ "$GATEZO_DEMO_SEED_ON_BOOT" = "true" ]; then
  su-exec php artisan gatezo:demo-reset --no-interaction || true
fi

exec "$@"
