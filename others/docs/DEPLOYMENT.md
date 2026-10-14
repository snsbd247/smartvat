# PLASTEX ERP — Deployment Guide (Ubuntu Server LTS)

Native deployment on a single Ubuntu Server 24.04 LTS host. Portable to another host/VPS
by repeating these steps and restoring a backup.

## 1. Provision
```bash
sudo bash deploy/install.sh     # PHP 8.3, MySQL 8, Redis, Nginx, Node 20, Composer
sudo mysql < deploy/mysql/secure-setup.sql   # edit the app-user password first
```

## 2. Application
```bash
sudo mkdir -p /var/www/plastex && sudo chown -R "$USER" /var/www/plastex
git clone <repo-url> /var/www/plastex
cd /var/www/plastex/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env            # set APP_ENV=production, APP_DEBUG=false, APP_URL,
                                # DB_CONNECTION=mysql + DB_*, REDIS_*, BACKUP_* (see below)
php artisan key:generate
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

### Frontend (SPA)
```bash
cd /var/www/plastex/frontend
npm ci
bash /var/www/plastex/others/deploy/build-local.sh   # builds and publishes into backend/public
```
> Set the SPA base path in `frontend/vite.config.ts` to `/` for a root-domain deployment
> (the local XAMPP preview uses `/irlvat`). Rebuild after changing it.

## 3. Web server
```bash
sudo cp deploy/nginx/plastex.conf /etc/nginx/sites-available/plastex
sudo ln -s /etc/nginx/sites-available/plastex /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## 4. Queue worker + scheduler
```bash
sudo cp deploy/systemd/plastex-queue.service /etc/systemd/system/
sudo cp deploy/systemd/plastex-scheduler.service /etc/systemd/system/
sudo cp deploy/systemd/plastex-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now plastex-queue plastex-scheduler.timer
```
The scheduler runs `backup:run` every 20 minutes (see `routes/console.php`).

## 5. Permissions, HTTPS, firewall
```bash
sudo chown -R www-data:www-data /var/www/plastex/backend/storage /var/www/plastex/backend/bootstrap/cache
sudo apt-get install -y certbot python3-certbot-nginx && sudo certbot --nginx
sudo ufw allow 'Nginx Full' && sudo ufw allow OpenSSH && sudo ufw enable
```

## 6. Backup configuration (`.env`)
```
BACKUP_DRIVER=google            # or 'local' for staging
BACKUP_RETENTION_DAYS=30
BACKUP_ENCRYPTION_KEY=<a long random secret, stored SEPARATELY from backups>
GOOGLE_DRIVE_FOLDER_ID=<dedicated Drive folder id>
GOOGLE_DRIVE_CREDENTIALS=/etc/plastex/google-service-account.json   # least-privilege
```
> Google Drive uses a least-privilege **service account** scoped to one folder. The
> `GoogleDriveStorage` driver is a guarded stub — wire the Drive API calls there before
> switching `BACKUP_DRIVER=google` in production. Until then `local` keeps verified,
> encrypted artifacts on disk.

## 7. Restore
```bash
bash deploy/restore.sh          # decrypts + verifies the latest backup and loads it
```

## Upgrade / rollback
```bash
cd /var/www/plastex && git pull
cd backend && composer install --no-dev -o && php artisan migrate --force
php artisan config:cache && php artisan route:cache
cd ../web && npm ci && bash ../deploy/build-local.sh
sudo systemctl restart plastex-queue
# Rollback: git checkout <previous-tag>, re-run the above, and if a migration must be
# undone use `php artisan migrate:rollback` (posted accounting data is never deleted).
```

## Notes
- A single-server deployment has **downtime if the host fails**. Google Drive backups
  protect recoverability, not availability — a live standby is a separate future step.
- Never commit secrets. `APP_KEY`, DB passwords, `BACKUP_ENCRYPTION_KEY` and the Google
  service-account JSON live only in the server environment / a secret store.
