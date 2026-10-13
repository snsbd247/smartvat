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
- [x] **Phase 8** — Sales Local (COMPLETE)
  - [x] customers (CRUD, policy); sales_orders(+lines); sales_invoices(+lines); delivery_challans(+lines); customer_receipts; sales_returns(+lines)
  - [x] `SalesService`: invoice (Dr AR/Cr Revenue, party=customer) → **delivery challan** (separate doc; stock out at avg cost + Dr COGS/Cr Inventory; partial deliveries) → receipt (Dr Cash|Bank/Cr AR) → return/credit note (goods back + Dr Revenue/Cr AR)
  - [x] `CustomerLedgerService`: outstanding + ledger from AR party lines
  - [x] Tests: invoice≠challan, partial delivery, COGS reconciled, receipt, return
- [x] **Phase 9** — Sales Export + FX (COMPLETE)
  - [x] Export invoice in foreign currency → AR/revenue booked in base currency at the rate (export_revenue account)
  - [x] Export realization: exchange **gain/loss** between invoice rate and realization rate posted to exchange_gain_loss; AR fully relieved
  - [x] Account map: sales_revenue, export_revenue, exchange_gain_loss
  - [x] Endpoints: customers apiResource; sales-invoices, delivery-challans, customer-receipts, sales-returns, customers/{id}/ledger
  - [x] Tests: **77 Pest green total** (+8: local + export paths; gain and loss)
  - Deferred: sales quotation, sales commission, export shipping docs (EXP/COO/shipping bill) tracking, aging buckets
- [x] **Phase 10** — Manufacturing (COMPLETE)
  - [x] boms(+lines, input-output coefficient, versioned), production_orders (planned/produced/scrap/wip_value)
  - [x] `ProductionService`: createBom/createOrder; issueMaterials (stock out of raw at avg + Dr WIP/Cr Inventory; BOM scaled to planned qty or explicit lines); receiveFinished (FG in at WIP/qty cost + Dr Inventory/Cr WIP; WIP nets to 0; yield loss absorbed into unit cost)
  - [x] Endpoints (module:production gated): boms, production-orders(+issue,+receive)
  - [x] Account map: wip
  - [x] Tests: issue→WIP, FG receipt reconciled + WIP zero, yield-loss absorption
- [x] **Phase 11** — Accounting completion (COMPLETE)
  - [x] cost_centers (table/model); manual journal entry (via PostingService) + reverse; fund transfer; CoA create/list; period status (open/closed/locked)
  - [x] `AccountingReportService`: Profit & Loss, **Balance Sheet (asset = liab + equity + net profit, in_balance)**, cash/bank book; trial balance
  - [x] Domain exceptions (unbalanced/period-closed/insufficient-stock/invalid) render as **422** for API clients
  - [x] Endpoints: accounting/accounts, cost-centers, journals(+reverse), fund-transfers, trial-balance, profit-loss, balance-sheet, accounts/{id}/cash-book, periods(+status)
  - [x] Tests: **84 Pest green total** (+7: P&L, balance-sheet balances, fund transfer, unbalanced→422)
  - Deferred: journal approval workflow, bank reconciliation, budget vs actual, year-end close automation
- [x] **Phase 12** — Fixed Assets (COMPLETE)
  - [x] asset_categories (holds asset/expense/accumulated GL accounts), fixed_assets, depreciation_entries
  - [x] `FixedAssetService`: acquire (Dr Asset / Cr cash|bank|payable); straight-line monthly depreciation (Dr Dep expense / Cr Accumulated), **idempotent per asset+month**, capped at depreciable base; runDepreciation for all active assets
  - [x] Endpoints: asset-categories, fixed-assets (acquire/list), fixed-assets/run-depreciation
  - [x] Tests: acquire posting, one-month depreciation, idempotency
- [x] **Phase 13** — VAT/Mushak + **Mushak 9.1 Note-number mapping engine** (COMPLETE)
  - [x] vat_rates, vat_transaction_types, vat_tax_treatments, vat_return_notes (verified data), vat_note_mappings (configurable, versioned, effective-dated, approved+verified), vat_transaction_classifications (frozen rule version)
  - [x] `VatClassifier`: generic evaluator — **no hard-coded Note Number / note-to-subform logic**; VAT rate alone never determines the note (transaction type + treatment are part of the key); specificity + effective-date resolution; unapproved/unverified mappings ignored
  - [x] **Unmapped → "review_required", never a guessed Note Number**
  - [x] `VatReportService`: Mushak 9.1 note-wise summary, unmapped-transaction report, input/output register
  - [x] Endpoints: rates/transaction-types/tax-treatments/notes/mappings (+approve = practitioner-verify), classify, note-summary, unmapped, register
  - [x] Tests: **92 Pest green total** (+8: acquire/depreciation/idempotent; classify-verified, review_required, rate-not-sole-determinant, unapproved-ignored, summary+unmapped)
  - Deferred: VAT return preparation & Mushak document register/print, VAT↔GL reconciliation, auto-classify wired into sales/purchase posting
- [x] **Phase 14** — VDS (COMPLETE)
  - [x] vds_rules (configurable, versioned, effective-dated, approved+verified), vds_deductions, vds_certificates, vds_treasury_deposits
  - [x] `VdsService`: rateFor (verified rule only, else null — **no hard-coded rate**); deduct (Dr AP supplier / Cr VDS payable); issueCertificate; depositToTreasury (Dr VDS payable / Cr Cash|Bank); deducted-vs-deposited reconciliation
  - [x] Account map: vds_payable
  - [x] Tests: refuse without verified rule, unapproved ignored, deduct posting, deposit reconciliation, certificate
- [x] **Phase 15** — Contract Manufacturing / Job Work (COMPLETE)
  - [x] manufacturing_contracts, job_work_orders, processing_invoices
  - [x] `JobWorkService`: customer material receive/consume/deliver via stock engine with **owner_type=customer** — never company inventory/asset, **no GL for custody**; processing invoice charges the conversion **service** (Dr AR / Cr processing income), not goods; material reconciliation (received − consumed/delivered = balance)
  - [x] Account map: processing_income
  - [x] Tests: **100 Pest green total** (+8) — custody has no journal entry & company reconciliation unaffected; material reconciliation; processing invoice is service revenue
  - Deferred: customer-owned FG ownership-transfer workflow, scrap ownership posting, job-work costing report
- [x] **Phase 16** — Reporting engine (COMPLETE)
  - [x] `ReportDefinition` + `ReportEngine` (HTML via Blade, PDF via dompdf, XLSX via PhpSpreadsheet) — one definition → every format
  - [x] `ReportRegistry`: trial-balance, profit-loss, balance-sheet, stock-valuation, vat-note-summary (reuse existing services)
  - [x] Single secured route `GET /api/reports/{key}?format=json|html|pdf|xlsx` — authorized + tenant-scoped; files streamed, never at public URLs
  - [x] Tests: list, JSON, XLSX (content-type), PDF (content-type), 403 without reports.view
  - Note: dompdf used for portability (CI-safe); Browsershot can be swapped for pixel-perfect Mushak forms later
- [x] **Phase 17** — MIS dashboards + Approval Center (COMPLETE)
  - [x] `MisService`: executive KPIs (revenue, expense, net profit, receivables, payables, VDS payable, stock value, cash/bank, pending approvals, unmapped VAT) — read-only
  - [x] Approval Center: pending draft purchase/sales/production orders
  - [x] Endpoints: mis/executive, mis/approvals
  - [x] Tests: **107 Pest green total** (+7) — executive KPIs, pending approvals
  - Deferred: per-module drill-down dashboards wired in SPA, saved filters
- [ ] Phase 18 — Google Drive backup/restore
- [ ] Phase 19 — Hardening + E2E + production readiness

## Legal/compliance watch (must stay true)
- No statutory VAT/Mushak/9.1 Note/VDS/customs rate, threshold, mapping, or deadline is
  hard-coded or invented. All such rules are configurable, versioned, effective-dated,
  and flagged "Requires practitioner review" until verified & approved.
- Unmapped transactions → "Review Required", never auto-classified into a guessed line.

## Notes / blockers
- (none yet)
