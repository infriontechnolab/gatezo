# Server setup (one VPS: GCP Compute Engine e2-small or Hostinger KVM 1)

Ubuntu 24.04, ~₹400–800/mo, always warm. Two ways to run it; Docker is the default.

## DNS for gatezo.in

At the registrar, point the domain straight at the server's IPv4 — no proxy/CDN in front, or
Caddy cannot complete the ACME challenge and the scanner's offline sync gets an extra hop:

| Type | Name | Value |
|---|---|---|
| A | `@` | server IP |
| A | `www` | server IP |

Then `dig +short gatezo.in` from anywhere should return that IP before you start the stack.
Caddy issues and renews the certificate itself; nothing else to configure. `www` is not
redirected by default — add `www.gatezo.in` to the Caddyfile site line if you want it to work.

## A. Docker (default)

Needs only Docker on the box. Caddy gets the Let's Encrypt certificate by itself once
DNS for the domain points at the server (A record, no proxy).

```bash
# 1. docker
curl -fsSL https://get.docker.com | sudo sh && sudo usermod -aG docker $USER && newgrp docker

# 2. app
git clone git@github.com:infriontechnolab/gatezo.git ~/gatezo && cd ~/gatezo
cp .env.example .env
docker compose -f compose.prod.yml run --rm app php artisan key:generate --show   # paste into APP_KEY
# edit .env: APP_ENV=production APP_DEBUG=false APP_URL=https://gatezo.in
#            GATEZO_DOMAIN=gatezo.in TRUSTED_PROXIES=* LOG_CHANNEL=stderr SESSION_SECURE_COOKIE=true
#            DB_PASSWORD / DB_ROOT_PASSWORD (any strong strings; DB_HOST is set by compose)
#            MAIL_* (Zoho SMTP, from hello@infrion.in)  GATEZO_DEMO=true GATEZO_DEMO_SEED_ON_BOOT=true

# 3. up
docker compose -f compose.prod.yml up -d --build
docker compose -f compose.prod.yml logs -f app        # migrations, caches, demo seed, then supervisord

# 4. your staff login, then the first organizer
docker compose -f compose.prod.yml exec app php artisan gatezo:admin you@example.com --name="Your Name"   # prints a set-password link for /ops
docker compose -f compose.prod.yml exec app php artisan gatezo:organizer "Name" client@example.com --event="First event"
```

Check after the first boot: `https://gatezo.in` (padlock), `/admin/register` (sign-up), `/ops` (staff),
`/e/<slug>` on a phone (camera + Add to Home Screen), and that an uploaded event logo appears —
if it doesn't, `php artisan storage:link` never ran.

What runs: `caddy` (80/443, TLS) → `app` (nginx + php-fpm + queue worker + `schedule:work`) → `db` (MySQL 8.4).
Uploads live in the `app_storage` volume, data in `db_data`, certificates in `caddy_data`.

Deploy an update:

```bash
cd ~/gatezo && git pull --ff-only && docker compose -f compose.prod.yml up -d --build
```

Backups (nightly dump kept 14 days; copy the folder off-box or to GCS/R2):

```bash
( crontab -l 2>/dev/null; echo "30 2 * * * cd ~/gatezo && docker compose -f compose.prod.yml exec -T db sh -c 'mysqldump -ugatezo -p\$MYSQL_PASSWORD gatezo' | gzip > ~/backups/gatezo-\$(date +\%F).sql.gz && find ~/backups -mtime +14 -delete" ) | crontab -
```

## B. Bare metal (nginx + php-fpm + supervisor)

Same box runs nginx, PHP-FPM, MySQL, queue worker.

```bash
# 1. packages
sudo apt update && sudo apt install -y nginx mysql-server supervisor git unzip \
  php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-gd php8.4-intl php8.4-bcmath php8.4-zip
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# 2. database
sudo mysql -e "CREATE DATABASE gatezo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'gatezo'@'localhost' IDENTIFIED BY 'CHANGE_ME';
  GRANT ALL ON gatezo.* TO 'gatezo'@'localhost';"

# 3. app
sudo mkdir -p /var/www && cd /var/www
sudo git clone git@github.com:infriontechnolab/gatezo.git && sudo chown -R $USER:www-data gatezo
cd gatezo && cp .env.example .env   # fill APP_URL, DB_*, mail
composer install --no-dev --optimize-autoloader && php artisan key:generate
npm ci && npm run build
php artisan migrate --force && php artisan storage:link
sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R ug+rwx storage bootstrap/cache

# 4. nginx + supervisor + cron
sudo cp infra/nginx.conf /etc/nginx/sites-available/gatezo   # edit server_name
sudo ln -s /etc/nginx/sites-available/gatezo /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
sudo cp infra/supervisor-gatezo.conf /etc/supervisor/conf.d/gatezo.conf && sudo supervisorctl reread && sudo supervisorctl update
( crontab -l 2>/dev/null; echo "* * * * * cd /var/www/gatezo && php artisan schedule:run >> /dev/null 2>&1" ) | crontab -

# 5. TLS: Cloudflare proxied (orange cloud) in front, or:
sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d gatezo.example.com

# 6. backups (nightly dump → object storage; pick GCS or R2)
( crontab -l; echo "30 2 * * * mysqldump -u gatezo -pCHANGE_ME gatezo | gzip > /var/backups/gatezo-\$(date +\%F).sql.gz" ) | crontab -
```

Subsequent deploys: `bash infra/deploy.sh`. Behind Cloudflare set `TRUSTED_PROXIES=*` in `.env`.

First organizer: `php artisan gatezo:organizer "Name" you@example.com --event="First event"`
(sign-up is invite-only; the landing page sends people to WhatsApp).
