# Universal Business ERP

A multi-company, configurable **Universal Business ERP** for Bangladesh —
Manufacturing, Service Provider, Trading, and hybrids — with Bangladesh
VAT/Mushak/VDS compliance, import/export trade, inventory, production, double-entry
accounting, contract manufacturing (customer-owned material), MIS, and Google Drive
backup.

> **HR & Payroll is intentionally excluded.** User management is included.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the full architecture and
[`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for current progress.

## Monorepo layout
```
api/     Laravel 12 REST API (PHP 8.2 dev / 8.3 prod target), Sanctum, spatie/permission
web/     React 19 + TypeScript + Vite + Tailwind SPA
deploy/  Ubuntu install/deploy scripts, nginx, systemd, backup (added in later phases)
docs/    Architecture, ERD, data dictionary, project status, runbook
```

## Tech stack
- **Backend:** Laravel 12, MySQL 8 (InnoDB) — SQLite used for local dev/tests, Redis 7
- **Auth:** Laravel Sanctum · **RBAC:** spatie/laravel-permission
- **Frontend:** React 19 + TypeScript, Vite, Tailwind CSS, shadcn/ui, Lucide, TanStack Query
- **Tests:** Pest (backend), Vitest + Testing Library (frontend), Playwright (E2E, later)
- **API docs:** OpenAPI (`api/openapi/openapi.yaml`)

## Local development

### Backend (`api/`)
```bash
cd api
composer install
cp .env.example .env         # already present after scaffold
php artisan key:generate
php artisan migrate           # uses SQLite by default for dev
php artisan serve             # http://127.0.0.1:8000
./vendor/bin/pest             # run tests
```
Health check: `GET http://127.0.0.1:8000/api/health`

### Frontend (`web/`)
```bash
cd web
npm install
npm run dev                   # http://127.0.0.1:5173
npm run test -- --run         # Vitest
npm run build                 # type-check + production build
```

## Browse locally via XAMPP (Apache) at `/irlvat`

Apache serves the whole app (React SPA + Laravel API) under `/irlvat`:

- http://localhost/irlvat
- http://160.25.9.139/irlvat  (same machine's IP)

Setup already applied on this machine:
- Apache alias `/irlvat` → `api/public` (in `xampp/apache/conf/extra/httpd-vhosts.conf`)
- Laravel serves the SPA for non-API routes; `/irlvat/api/*` is the REST API
- SQLite dev database, seeded demo data

After any **frontend** change, rebuild and republish the SPA into Laravel, then it's live:
```bash
bash deploy/build-local.sh        # builds web/ and copies into api/public + spa view
```
(If Apache config changes: `xampp/apache/bin/httpd.exe -k restart`.)

**Demo login:** `admin@irlbd.com` / `admin@123` (super-admin, access to all demo companies:
ACME manufacturing, BETA trading, GAMMA services).

> Note: this XAMPP/Apache setup is a local preview. Production remains the documented
> native Ubuntu deployment (`deploy/` scripts, later phase). The login screen and full
> module UIs are built in later frontend phases — the API login already works (tested).

## Status
Phase 1 (project skeleton) in progress. Production deployment (MySQL, Nginx, HTTPS,
scheduler, backup) is delivered in later phases — see the phase checklist in
`docs/PROJECT_STATUS.md`.

## Compliance note
No statutory VAT/Mushak/VDS/customs rate, threshold, Note Number, mapping, or deadline
is hard-coded. All such rules are configurable, versioned, effective-dated, and must be
verified and approved by a qualified Bangladesh VAT/tax practitioner before production
use. Unmapped transactions are flagged for review, never silently classified.
