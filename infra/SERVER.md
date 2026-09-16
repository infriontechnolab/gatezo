# Server setup (one VPS: GCP Compute Engine e2-small or Hostinger KVM 1)

Ubuntu 24.04, ~₹400–800/mo, always warm. Same box runs nginx, PHP-FPM, MySQL, queue worker.

```bash
# 1. packages
sudo apt update && sudo apt install -y nginx mysql-server supervisor git unzip \
  php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-gd php8.4-intl php8.4-bcmath php8.4-zip
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# 2. database
sudo mysql -e "CREATE DATABASE eventqr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'eventqr'@'localhost' IDENTIFIED BY 'CHANGE_ME';
  GRANT ALL ON eventqr.* TO 'eventqr'@'localhost';"

# 3. app
sudo mkdir -p /var/www && cd /var/www
sudo git clone git@github.com:infriontechnolab/eventqr.git && sudo chown -R $USER:www-data eventqr
cd eventqr && cp .env.example .env   # fill APP_URL, DB_*, PASS_TOKEN_SECRET, mail
composer install --no-dev --optimize-autoloader && php artisan key:generate
npm ci && npm run build
php artisan migrate --force && php artisan storage:link
sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R ug+rwx storage bootstrap/cache

# 4. nginx + supervisor + cron
sudo cp infra/nginx.conf /etc/nginx/sites-available/eventqr   # edit server_name
sudo ln -s /etc/nginx/sites-available/eventqr /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
sudo cp infra/supervisor-eventqr.conf /etc/supervisor/conf.d/eventqr.conf && sudo supervisorctl reread && sudo supervisorctl update
( crontab -l 2>/dev/null; echo "* * * * * cd /var/www/eventqr && php artisan schedule:run >> /dev/null 2>&1" ) | crontab -

# 5. TLS: Cloudflare proxied (orange cloud) in front, or:
sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d eventqr.example.com

# 6. backups (nightly dump → object storage; pick GCS or R2)
( crontab -l; echo "30 2 * * * mysqldump -u eventqr -pCHANGE_ME eventqr | gzip > /var/backups/eventqr-\$(date +\%F).sql.gz" ) | crontab -
```

Subsequent deploys: `bash infra/deploy.sh`.

First organizer: open `https://<domain>/admin/register`. Turn `->registration()` off in
`AdminPanelProvider` once you have your accounts if you don't want public sign-up.
