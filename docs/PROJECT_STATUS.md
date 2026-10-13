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
- [x] **Phase 5** — MasterData + Inventory ops (COMPLETE)
  - [x] MasterData: units + unit_conversions + product_categories; products extended (category_id, reorder_level); Product CRUD (policy, tenant-unique code)
  - [x] `company_account_map` + `AccountResolver` (posting roles → GL accounts, configurable)
  - [x] `inventory_transactions` header; `InventoryService`: receipt / issue / transfer / adjustment — **atomic across Stock + Posting engines**
  - [x] `InventoryReportService`: valuation, stock ledger, **Inventory↔GL reconciliation**
  - [x] Endpoints (gated by module:inventory): POST inventory/receipts|issues|adjustments|transfers; GET inventory/stock|ledger|reconciliation; products apiResource
  - [x] DemoSeeder: GRN-clearing + inventory-adjustment accounts + account map per company
  - [x] Tests: **54 Pest green total** (+8: receipt/issue/transfer/adjustment reconcile to GL, oversell rollback, product CRUD authZ, module-gated inventory ops)
  - Deferred: batch/expiry tracking flows, physical count UI, UoM-conversion on transactions, opening-stock import
- [x] **Phase 6** — Purchase Local (COMPLETE)
  - [x] journal_lines gains party_type/party_id (supplier/customer ledger dimension; PostingService carries it)
  - [x] suppliers (CRUD, policy); purchase_orders(+lines), goods_receipt_notes(+lines), purchase_invoices(+lines), supplier_payments
  - [x] `PurchaseService`: PO (draft→approve, no GL) → GRN (stock in + Dr Inventory/Cr GRN-clearing) → Purchase Invoice (material: Dr GRN-clearing/Cr AP; service: Dr Expense/Cr AP) → Supplier Payment (Dr AP/Cr Cash|Bank)
  - [x] `SupplierLedgerService`: outstanding + ledger from AP party lines
  - [x] Endpoints: suppliers apiResource; purchase-orders(+approve), goods-receipts, purchase-invoices, supplier-payments, suppliers/{id}/ledger
  - [x] Account map: accounts_payable, accounts_receivable added
  - [x] Tests: **61 Pest green total** (+7: PO approve, GRN reconciled, material/service invoice→AP, payment reduces outstanding, trial balance balanced, supplier CRUD authZ, API flow)
  - Deferred: purchase requisition/quotation, purchase return, price comparison, VAT/VDS split (Phase 13/14), aging buckets
- [x] **Phase 7** — Purchase Import + Landed Cost (COMPLETE)
  - [x] import_shipments(+lines), import_charges, landed_cost_allocations; foreign currency + exchange rate
  - [x] `LandedCostService`: allocate charges by quantity/weight/volume/value basis; recoverable charges excluded from inventory; last-line remainder for exact totals; idempotent (delete+reinsert — never double-allocates)
  - [x] `ImportService`: createShipment, preview (no-post), receive → stock in at landed unit cost + Dr Inventory / Cr GRN-clearing (goods) / Cr Landed-cost-clearing (non-recoverable); received-once guard
  - [x] Endpoints: import-shipments (store), {id}/landed-cost (preview), {id}/receive
  - [x] Account map: landed_cost_clearing
  - [x] Tests: **69 Pest green total** (+8: value/quantity-basis allocation, FX conversion, recoverable excluded, idempotent, receive→GL reconciled, double-receive blocked, API flow)
  - Deferred: import PO/proforma/LC-TT/customs-doc tracking, per-charge supplier invoicing of landed costs, import cost reconciliation report
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
