# Server setup (one VPS: GCP Compute Engine e2-small or Hostinger KVM 1)

Ubuntu 24.04, ~₹400–800/mo, always warm. Two ways to run it; Docker is the default.

## Buying the box (Contabo, Mumbai)

Cloud VPS 4 in **Asia (India)**: 4 vCPU, 8 GB RAM, 100 GB SSD, ~€7.90/mo including the
India location fee. Image **Ubuntu 24.04**, and paste your SSH public key at checkout if
the form offers it. Provisioning is not instant — Contabo can take anywhere from minutes
to a few hours, and the root password arrives by email.

The trade-off we accepted: Contabo oversells, so disk IOPS vary with the neighbours. Two
things in this repo compensate — `DB_BUFFER_POOL=2G` keeps the whole database in RAM so
reads never hit the disk, and the scanner is offline-first so a slow server never stops a
gate. Do one full dry-run event before a paying client's night.

## First 10 minutes on the box

```bash
ssh root@<ip>                      # password from Contabo's email
curl -fsSL https://raw.githubusercontent.com/infriontechnolab/gatezo/main/infra/provision.sh -o provision.sh
bash provision.sh deploy "ssh-ed25519 AAAA... you@laptop"
```

Creates the `deploy` user with your key, disables root and password SSH, opens 80/443 only,
adds 2 GB swap (Contabo images ship without any, and an OOM kill mid-event is the worst
possible failure), installs Docker, turns on fail2ban and unattended security upgrades.

**Open a second terminal and confirm `ssh deploy@<ip>` works before closing the root one.**

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
#            DB_BUFFER_POOL=2G   (8 GB box; MySQL keeps the database in RAM)
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

Backups — `infra/backup.sh` dumps the database *and* the uploaded files, keeps 14 days
locally and pushes both to any S3-compatible bucket (Cloudflare R2 is ~free at this size).
Put the `BACKUP_S3_*` keys in `.env`, then:

```bash
bash ~/gatezo/infra/backup.sh                                     # run once by hand first
(crontab -l 2>/dev/null; echo "30 2 * * * bash \$HOME/gatezo/infra/backup.sh >> \$HOME/backups/backup.log 2>&1") | crontab -
```

Restore: `gunzip -c gatezo-db-<stamp>.sql.gz | docker compose -f compose.prod.yml exec -T db mysql -ugatezo -p<pass> gatezo`

**A backup you have never restored is not a backup.** Restore one into your local database
once, before the first real event.

## Watch it

Free and worth the five minutes: point [UptimeRobot](https://uptimerobot.com) or
[Better Stack](https://betterstack.com) at `https://gatezo.in` with a 5-minute check and
SMS/WhatsApp alerts. On an oversold host you want to hear about a bad node from a monitor,
not from an organizer standing at a gate.

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
