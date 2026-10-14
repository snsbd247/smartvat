#!/usr/bin/env bash
# PLASTEX ERP — provision a fresh Ubuntu Server LTS host (run as a sudo user).
# Installs PHP 8.3, MySQL 8, Redis, Nginx, Node 20, Composer. Idempotent-ish.
set -euo pipefail

echo "==> Updating apt and installing prerequisites"
sudo apt-get update -y
sudo apt-get install -y software-properties-common curl unzip git

echo "==> PHP 8.3 + extensions"
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update -y
sudo apt-get install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-redis php8.3-intl

echo "==> MySQL 8, Redis, Nginx"
sudo apt-get install -y mysql-server redis-server nginx

echo "==> Composer"
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

echo "==> Node 20 (for building the SPA)"
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

cat <<'NEXT'

==> Base packages installed. Next steps:
  1. Create the database and a least-privilege app user (see deploy/mysql/secure-setup.sql).
  2. Clone the repo to /var/www/plastex, then:
       cd /var/www/plastex/backend && composer install --no-dev --optimize-autoloader
       cp .env.example .env   # set APP_KEY, DB_*, REDIS_*, BACKUP_* etc.
       php artisan key:generate && php artisan migrate --force
       cd ../frontend && npm ci && bash ../others/deploy/build-local.sh   # or build + copy dist
  3. Install the Nginx site (deploy/nginx/plastex.conf) and reload nginx.
  4. Enable the queue worker + scheduler (deploy/systemd/*).
  5. Configure HTTPS:  sudo apt-get install -y certbot python3-certbot-nginx && sudo certbot --nginx
  6. Firewall:  sudo ufw allow 'Nginx Full' && sudo ufw allow OpenSSH && sudo ufw enable
See docs/DEPLOYMENT.md for the full guide.
NEXT
