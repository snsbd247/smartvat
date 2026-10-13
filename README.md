# Universal Business ERP (smartvat)

A multi-company, configurable **Universal Business ERP** for Bangladesh — serving
**Manufacturing, Service Provider, Trading, and hybrid** businesses through
company-specific configuration (not separate apps). Built for Bangladesh
**VAT / Mushak / VDS** compliance, import/export trade, inventory, production,
double-entry accounting, contract manufacturing (customer-owned material), MIS, and
Google Drive backup.

> **HR & Payroll is intentionally out of scope.** User management is included.

- **Architecture & design:** [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- **Live progress tracker:** [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md)
- **API spec:** [`api/openapi/openapi.yaml`](api/openapi/openapi.yaml)

---

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12 (REST API), PHP 8.2 (dev) / 8.3 (prod target) |
| Auth / RBAC | Laravel Sanctum · spatie/laravel-permission (teams = companies) |
| Database | MySQL 8 (InnoDB) in production · SQLite for local dev/tests |
| Cache / Queue | Redis 7 |
| Frontend | React 19 + TypeScript + Vite + Tailwind CSS 4 + shadcn/ui + Lucide |
| Testing | Pest (backend) · Vitest + Testing Library (frontend) · Playwright (E2E, later) |
| Precision | bcmath fixed-point money/quantity math (`Num` helper) |
| Web server | Nginx (prod) · Apache/XAMPP (local preview) |

Design principles: **modular monolith**, strict **multi-tenant isolation**
(`company_id` global scope + policy + module gate), two shared engines
(**double-entry Posting** + **Inventory Stock**) that everything posts through, and
**configurable, versioned, verified** statutory rules (nothing hard-coded).

---

## Repository layout
```
api/     Laravel 12 REST API (modular: app/Modules/<Domain>, app/Shared)
web/     React 19 + TS + Vite + Tailwind SPA (navy-sidebar PLASTEX-style shell)
deploy/  build/deploy scripts (build-local.sh for XAMPP; Ubuntu scripts: later)
docs/    ARCHITECTURE.md, PROJECT_STATUS.md, (ERD/data-dictionary: later)
```

---

## Roadmap & status

Legend: ✅ done & tested · 🔜 next · ⬜ planned

### Foundation & core (DONE — 100 backend tests green)
- ✅ **Phase 1 — Project skeleton**: Laravel + React/Vite/Tailwind, CI (GitHub Actions), `/api/health`, OpenAPI scaffold, JSON-only API middleware.
- ✅ **Phase 2 — Organization + Security + multi-tenant isolation**: companies, factories, warehouses, storage locations; users with **NID encrypted at rest + blind index**; Sanctum login; per-company RBAC; audit log; tenant isolation enforced (cross-tenant → 404/403).
- ✅ **Phase 3 — CompanyConfig (business-type engine + module gate)**: Setup via business types (manufacturing/service/trading + hybrids) & industry templates; **backend module gate** (not just menu hiding).
- ✅ **Phase 4 — Shared engines**: double-entry **PostingService** (balanced, idempotent, period-lock, reversal-only) + **StockService** (weighted-average, row-locked, no negative stock, customer-owned kept separate); trial balance; atomic per-company numbering.
- ✅ **Phase 5 — MasterData + Inventory ops**: products/categories/units; receipt / issue / transfer / adjustment — atomic across both engines; **Inventory↔GL reconciliation**.
- ✅ **Phase 6 — Purchase (Local)**: supplier → PO → GRN (stock+GL) → purchase invoice (material/service → AP) → payment; supplier ledger & outstanding (party-tagged AP lines).
- ✅ **Phase 7 — Purchase Import + Landed Cost**: FX shipments; **landed-cost allocation** (quantity/weight/volume/value basis, recoverable excluded, idempotent); import receive at landed cost → GL reconciled.
- ✅ **Phase 8 — Sales (Local)**: customer → sales invoice (Dr AR / Cr Revenue) → **delivery challan** (separate document; stock out at avg cost + COGS; partial deliveries) → customer receipt → sales return/credit note; customer ledger & outstanding.
- ✅ **Phase 9 — Sales Export + FX**: export invoice in foreign currency (AR/revenue in base currency at the rate) → realization booking **exchange gain/loss** between invoice and realization rates.
- ✅ **Phase 10 — Manufacturing**: BOM (versioned, input-output coefficient) + production orders; material issue → **WIP** (Dr WIP / Cr Inventory at avg cost); finished-goods receipt at WIP/qty cost (Dr Inventory / Cr WIP, WIP nets to zero); yield loss absorbed into unit cost.
- ✅ **Phase 11 — Accounting completion**: manual journal entry + reverse, fund transfer, cost centers, chart-of-accounts management, period open/close/lock; **Profit & Loss**, **Balance Sheet** (balances), cash/bank book, trial balance; domain errors return 422.

- ✅ **Phase 12 — Fixed Assets**: categories (with GL accounts), acquisition (Dr Asset / Cr cash·bank·payable), straight-line monthly depreciation (Dr Expense / Cr Accumulated), idempotent per asset+month.
- ✅ **Phase 13 — VAT / Mushak + Mushak 9.1 Note-number mapping engine**: rate/transaction-type/treatment/note masters + configurable, versioned, effective-dated, **practitioner-verified** note mappings; classifier where VAT rate alone never determines the note; **unmapped → "review required", never a guessed Note Number**; note-wise summary, unmapped report, input/output registers.

- ✅ **Phase 14 — VDS (VAT Deduction at Source)**: configurable, verified rules (no hard-coded rate); deduction (Dr AP / Cr VDS payable), certificate, treasury deposit, deducted-vs-deposited reconciliation.
- ✅ **Phase 15 — Contract Manufacturing / Job Work**: customer-owned material custody via the stock engine (owner=customer, never company inventory/asset, no GL for custody); processing invoice charges the conversion **service**, not goods; material reconciliation.

### Remaining
- 🔜 **Phase 16 — Reporting engine**: reusable PDF (preview + full-screen + new-tab + print + download) + XLSX export; secured document routes; Document Center.
- ⬜ **Phase 17 — MIS dashboards**: executive / purchase / sales / production / inventory / accounting / VAT-VDS / job-work, with filters + drill-down; Approval Center.
- ⬜ **Phase 18 — Google Drive backup/restore**: 20-min schedule, compress + encrypt, checksum-verified upload, 30-day retention (never deletes last good backup), restore test, alerts.
- ⬜ **Phase 19 — Hardening**: security pass, rate limiting, headers, E2E acceptance (Playwright), native Ubuntu deployment scripts, production-readiness checklist.

### Cross-cutting (as phases land)
- ⬜ Frontend module UIs (login screen, company switcher, auth-connected dashboards, per-module screens) — the SPA is currently a navigable shell; API is built first.
- ⬜ ERD + data dictionary documents; full OpenAPI coverage per module.

See [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the detailed per-phase checklist.

---

## What works today (API)

Procure-to-pay and inventory are functional end-to-end with full accounting:
companies & users, business-type/module configuration, chart of accounts & periods,
products, suppliers, local & import purchases, goods receipt, invoicing, payments, and
stock operations — every stock movement reconciles to the Inventory GL account and the
trial balance always balances. 69 automated tests cover these paths.

---

## Local development

### Backend (`api/`)
```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # SQLite by default for dev
php artisan serve               # http://127.0.0.1:8000
./vendor/bin/pest               # run tests
```

### Frontend (`web/`)
```bash
cd web
npm install
npm run dev                     # http://127.0.0.1:5173
npm run build                   # type-check + production build
npm run test -- --run           # Vitest
```

### Browse via XAMPP (Apache) at `/irlvat`
Apache serves the whole app (SPA + API) at `http://localhost/irlvat` and
`http://<server-ip>/irlvat`. Rebuild the SPA into Laravel after frontend changes:
```bash
bash deploy/build-local.sh
```

**Demo login:** `admin@irlbd.com` / `admin@123` (super-admin; demo companies: ACME
manufacturing, BETA trading, GAMMA services). Seed data uses fake values only.

---

## Compliance note
This is a Bangladesh-focused ERP. No statutory VAT/Mushak/VDS/customs rate, threshold,
Mushak 9.1 Note Number, note-to-subform mapping, filing deadline, or export-document
requirement is hard-coded or invented anywhere. All such rules are modeled as
**configurable, versioned, effective-dated data** that must be entered and approved by a
**qualified Bangladesh VAT/tax practitioner** before production use. Unmapped
transactions are flagged for review — never silently classified into a guessed return
line.

---
🤖 Built incrementally with [Claude Code](https://claude.com/claude-code) — phase by
phase, each phase shipped only with passing tests.
