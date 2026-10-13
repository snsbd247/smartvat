# Project Status — Universal Business ERP

> Persistent cross-session tracker. Update at the end of every work session / phase.
> Authoritative plan: [ARCHITECTURE.md](ARCHITECTURE.md). Scope: multi-company,
> configurable Universal Business ERP (Manufacturing / Service / Trading + hybrids),
> Bangladesh VAT/Mushak/VDS. **HR & Payroll is excluded.**

Last updated: 2026-10-13

## Confirmed technical decisions (defaults — change anytime before they're built)
- Backend: Laravel 12, PHP 8.2 (dev) / 8.3 (prod target), MySQL 8 InnoDB (SQLite for dev/test), Redis 7
- Frontend: React 19 + TS 6 + Vite 8 + Tailwind 4 (+ shadcn/ui + Lucide)
- Auth: Sanctum; RBAC: spatie/laravel-permission
- Deployment: native (Ubuntu 24.04 LTS)
- PDF: Browsershot (Phase 16); Drive auth: service account (Phase 18)
- Tenancy: one user may access multiple companies (switchable company context)
- UI language: English first, i18n-ready (Bangla later)
- First industry template: Plastic Manufacturing

## Open decisions still pending owner confirmation (§15.3 of ARCHITECTURE)
1. PDF renderer (Browsershot vs dompdf) — assumed Browsershot
2. Deployment (native vs Docker) — assumed native
3. Google Drive auth + which account/folder — assumed service account, folder TBD
4. Tenancy: multi-company per user? — assumed yes
5. Bangla UI in v1? — assumed no (English first)
6. Which industry templates at launch? — assumed Plastic only first

## Phase checklist
- [~] **Phase 0** — Architecture & planning (ARCHITECTURE.md) — awaiting formal sign-off; proceeding
- [x] **Phase 1** — Project skeleton (COMPLETE)
  - [x] Laravel 12 scaffolded in `api/`
  - [x] React 19 + TS + Vite + Tailwind 4 scaffolded in `web/`
  - [x] `/api/health` endpoint + tests (green; verified end-to-end via curl)
  - [x] Sanctum + spatie/permission installed; User model has HasApiTokens + HasRoles
  - [x] Pest (5 tests) + Vitest (2 tests) configured, all green
  - [x] ForceJsonResponse middleware (API always returns JSON 401/403, never redirect)
  - [x] Navy-sidebar SPA shell with 18 modules (HR excluded), dashboard shows live API/DB health
  - [x] CI workflow (GitHub Actions: backend Pest + frontend build/Vitest)
  - [x] OpenAPI scaffold (`api/openapi/openapi.yaml`)
  - [x] `.env.example` (api + web), README, .gitignore
  - Note: dev/test uses SQLite; MySQL wiring + Nginx/systemd/deploy scripts come in a later phase
- [x] **Phase 2** — Organization + Security + multi-tenant isolation (COMPLETE)
  - [x] Tenancy: `companies`, `user_company_access`; `CurrentCompany` context; `CompanyScope` global scope (fail-closed); `BelongsToCompany` trait; `ResolveCompany` middleware (X-Company-Id + access check → 403)
  - [x] Organization entities: company, factory, warehouse, storage_location (tenant-scoped); Factory full CRUD + Warehouse CRUD endpoints
  - [x] Security: user ERP fields; **NID encrypted at rest + blind index** (hidden from API); Sanctum login (email/username)/logout/me
  - [x] RBAC: spatie/permission with **teams = companies** (per-company roles); super-admin Gate::before; policies on Factory/Warehouse
  - [x] Audit log: `audit_logs` + `Auditable` trait + `AuditLogger` (applied to Factory/Warehouse; login events)
  - [x] Seeders: RolesAndPermissions (global defs) + Demo (2 companies, fake data)
  - [x] Tests: **20 Pest green** — tenant isolation (list scope, cross-tenant 404, unauthorized-company 403, company stamping), auth, authZ (viewer vs admin, per-company unique codes), NID encryption
  - Deferred to later phases: MFA enablement, StorageLocation endpoints, full user-management CRUD UI, factory-scope enforcement per-module
- [x] **Phase 3** — CompanyConfig (business-type engine + module gate) (COMPLETE)
  - [x] `company_business_types` + `company_modules` tables & models
  - [x] Module catalog + industry templates in `config/erp.php`
  - [x] `CompanyConfigurator` service: business types (+ primary, hybrid) + industry template → derived enabled modules
  - [x] `EnsureModuleEnabled` (`module:<key>`) middleware — backend module gate (not just menu hiding)
  - [x] Endpoints: GET /company/modules (manifest), GET/PUT /company/config (reconfigure, `company.configure` perm); warehouses gated by `module:inventory`
  - [x] DemoSeeder: ACME (manufacturing, 18 modules), BETA (trading, 16), GAMMA (service, 15)
  - [x] Tests: **33 Pest green total** (+13: configurator per type/hybrid/validation, module gate allow/deny, config endpoint authZ)
  - Deferred: company-creation (setup wizard first-step) endpoint + bootstrap super-admin; frontend wizard UI; per-module menu manifest wiring in SPA
- [x] **Phase 4** — Shared engines: Posting + Stock (COMPLETE)
  - [x] Accounting: chart_of_accounts, financial_years, accounting_periods, journal_entries, journal_lines
  - [x] `PostingService`: double-entry (balanced), idempotent per (source,purpose), period-lock aware, server-side, transactional; `reverse()` (mirror entry, original never mutated)
  - [x] `LedgerService`: account balance + balanced trial balance (includes posted+reversed)
  - [x] `Num` (bcmath precise decimals) + `NumberGenerator` (atomic per-company doc numbers via number_sequences)
  - [x] Inventory: products (minimal), stock_balances, stock_movements (append-only)
  - [x] `StockService`: weighted-average costing, row-locked (FOR UPDATE), prevents negative stock, customer-owned kept separate; FIFO guarded (not-yet-supported)
  - [x] DemoSeeder: default 9-account CoA + open FY2026 period per company
  - [x] Tests: **46 Pest green total** (+13: balanced/unbalanced/single-line/idempotency/period-lock/reversal/trial-balance; stock inbound/WA/outbound-valuation/oversell/customer-owned)
  - Note: true-concurrency safety relies on SELECT FOR UPDATE on MySQL (SQLite serializes); FIFO costing deferred
- [ ] Phase 5 — MasterData + Inventory ops
- [ ] Phase 6 — Purchase Local
- [ ] Phase 7 — Purchase Import + Landed Cost
- [ ] Phase 8 — Sales Local
- [ ] Phase 9 — Sales Export + FX
- [ ] Phase 10 — Manufacturing
- [ ] Phase 11 — Accounting completion
- [ ] Phase 12 — Fixed Assets
- [ ] Phase 13 — VAT/Mushak + Mushak 9.1 Note engine
- [ ] Phase 14 — VDS
- [ ] Phase 15 — Job Work (customer-owned)
- [ ] Phase 16 — Reporting engine (PDF/XLSX)
- [ ] Phase 17 — MIS dashboards + Approval Center
- [ ] Phase 18 — Google Drive backup/restore
- [ ] Phase 19 — Hardening + E2E + production readiness

## Legal/compliance watch (must stay true)
- No statutory VAT/Mushak/9.1 Note/VDS/customs rate, threshold, mapping, or deadline is
  hard-coded or invented. All such rules are configurable, versioned, effective-dated,
  and flagged "Requires practitioner review" until verified & approved.
- Unmapped transactions → "Review Required", never auto-classified into a guessed line.

## Notes / blockers
- (none yet)
