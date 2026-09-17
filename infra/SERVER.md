# Server setup (one VPS: GCP Compute Engine e2-small or Hostinger KVM 1)

Ubuntu 24.04, ~₹400–800/mo, always warm. Same box runs nginx, PHP-FPM, MySQL, queue worker.

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

Subsequent deploys: `bash infra/deploy.sh`.

First organizer: open `https://<domain>/admin/register`. Turn `->registration()` off in
`AdminPanelProvider` once you have your accounts if you don't want public sign-up.
