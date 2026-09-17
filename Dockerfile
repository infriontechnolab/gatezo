# Gatezo production image: one container runs nginx + php-fpm + queue worker + scheduler
# under supervisord. Pair it with MySQL and Caddy via compose.prod.yml.
#
#   docker build -t gatezo .
#   docker compose -f compose.prod.yml up -d

# ---- 1. PHP dependencies -------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Scripts need the full app (artisan, providers); install deps first for layer caching.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---- 2. Front-end assets ------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
# Tailwind scans these for class names; Filament's theme needs the vendor views too.
COPY app ./app
# The Filament theme imports base CSS from several vendor/filament packages.
COPY --from=vendor /app/vendor/filament ./vendor/filament
RUN npm run build

# ---- 3. Runtime ----------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS runtime
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd intl bcmath zip opcache pcntl \
 && apk add --no-cache nginx supervisor tzdata \
 && rm -rf /var/cache/apk/*

ENV TZ=Asia/Kolkata
WORKDIR /var/www/gatezo

COPY infra/docker/php.ini        /usr/local/etc/php/conf.d/gatezo.ini
COPY infra/docker/www.conf       /usr/local/etc/php-fpm.d/zz-gatezo.conf
COPY infra/docker/nginx.conf     /etc/nginx/http.d/default.conf
COPY infra/docker/supervisord.conf /etc/supervisord.conf
COPY infra/docker/entrypoint.sh  /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Filament publishes its CSS/JS into public/ (normally a composer post-autoload script).
RUN php artisan package:discover --no-interaction \
 && php artisan filament:assets --no-interaction \
 && mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache public \
 && rm -rf /var/www/gatezo/.env

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 CMD wget -qO- http://127.0.0.1/up >/dev/null || exit 1
ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
