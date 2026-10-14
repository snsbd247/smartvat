# cPanel deployment (shared hosting)

PLASTEX ERP runs on cPanel shared hosting with the Laravel app kept **outside**
`public_html` and a thin front controller in `public_html` that boots it. No
root access, Node, or a pre-installed Composer is required on the host — the SPA
is built locally and its static assets are uploaded.

Live instance: **https://easyvat365.xyz** (user `easyvat365`, home `/home/easyvat365`).

## Host requirements (verified on the live host)

- PHP 8.2+ with `pdo_mysql, bcmath, mbstring, openssl, tokenizer, ctype, fileinfo, json, curl, zip, gd`
- `git`, `mysql` client, and cPanel's `uapi` (for creating the DB over SSH)
- SSH access (PuTTY `plink`/`pscp` used from Windows)

## 1. Database (over SSH, via uapi)

```bash
uapi Mysql create_database name=<cpuser>_erp
uapi Mysql create_user name=<cpuser>_erp password='<STRONG_PW>'
uapi Mysql set_privileges_on_database user=<cpuser>_erp database=<cpuser>_erp privileges=ALL
```

`status: 1` means success. cPanel prefixes names with `<cpuser>_`.

## 2. App code + dependencies

```bash
cd ~ && git clone --depth 1 https://github.com/snsbd247/smartvat.git smartvat
# Composer installed locally (no system composer):
mkdir -p ~/bin && cd ~ && php -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
  && php composer-setup.php --install-dir=$HOME/bin --filename=composer && rm composer-setup.php
cd ~/smartvat/backend && ~/bin/composer install --no-dev --optimize-autoloader --no-interaction
```

## 3. Environment

Create `~/smartvat/backend/.env` (production), then:

```bash
cd ~/smartvat/backend
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=DemoSeeder --force   # demo companies; skip for a clean tenant
php artisan config:cache && php artisan view:cache
chmod -R ug+rwX storage bootstrap/cache
```

Key `.env` values: `APP_ENV=production`, `APP_DEBUG=false`,
`APP_URL=https://<domain>`, `DB_*` from step 1, `SESSION_DRIVER=database`,
`CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.

> **Do not** run `php artisan route:cache` — the SPA catch-all in `routes/web.php`
> is a closure and cannot be serialized.

## 4. Front controller in public_html

`public_html/index.php` boots the app from `../smartvat/backend` (see this file's
git history for the exact contents). Also copy Laravel's `public/.htaccess` and
`public/robots.txt` into `public_html/`. Keep `.well-known` and `cgi-bin`.

## 5. Frontend (built locally, uploaded)

```bash
cd frontend && APP_BASE=/ MSYS_NO_PATHCONV=1 npx vite build   # root base for a dedicated domain
# upload:
#   dist/assets/*      -> ~/public_html/assets/
#   dist/favicon.svg, dist/icons.svg -> ~/public_html/
#   dist/index.html    -> ~/smartvat/backend/resources/views/spa.blade.php
```

The SPA is served by Laravel's `spa` view; `/assets/*`, `/favicon.svg` etc. are
static files served directly by Apache.

## 6. Scheduler (20-minute encrypted backups)

```
* * * * * cd /home/<cpuser>/smartvat/backend && /usr/local/bin/php artisan schedule:run >> storage/logs/schedule.log 2>&1
```

`backup:run` dumps the DB, gzips, AES-256-encrypts, sha256-verifies and stores
it (currently `BACKUP_DISK=local`; wire the Google Drive driver for off-site).

## Redeploying after a push

```bash
cd ~/smartvat && git pull origin main
cd backend && ~/bin/composer install --no-dev -o && php artisan migrate --force \
  && php artisan config:cache && php artisan view:cache
# rebuild + re-upload the SPA if the frontend changed (step 5)
```

## Secrets

Live DB password and `APP_KEY` live only in the server's `~/smartvat/backend/.env`
— never commit them. SSH/host credentials are kept out of the repo.
