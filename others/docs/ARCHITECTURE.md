# Universal Business ERP — Architecture & Planning (Phase 0)

> **Status:** DRAFT for approval. No application code is written yet.
> Supersedes the earlier plastic-manufacturing-only draft. Scope is now a
> **configurable, multi-company Universal Business ERP** for Bangladesh serving
> **Manufacturing, Service Provider, Trading, and hybrids**, with the first target
> industry being plastic manufacturing.
>
> **HR & Payroll is OUT OF SCOPE** and will not be built. User Management stays in.
>
> This document covers the 15 "First Task" deliverables (§27 of the spec).
>
> **Legal note (applies throughout):** no statutory VAT/Mushak/VDS/customs rate,
> threshold, Note Number, note-to-subform mapping, form applicability, deadline, or
> export-document requirement is asserted as fact anywhere in this plan. Every such
> rule is modeled as **configurable, versioned, effective-dated data** that a qualified
> Bangladesh VAT/tax practitioner must enter and approve before production use.
> Unverified rules are flagged "Requires review"; unmapped transactions are never
> silently classified into a guessed return line. See §8, §9.

---

## 1. System Architecture

### 1.1 Shape: Multi-tenant Modular Monolith
One Laravel REST API + one React/TypeScript SPA, organized into bounded **domain
modules**. **Multi-company with strict tenant isolation**: every tenant row carries
`company_id`, enforced by a global query scope *and* policy checks — one company can
never read or post against another's data. No microservices; a modular monolith with
enforced module boundaries (a module calls another module's service, never writes its
tables directly).

```
┌────────────────────────────────────────────────────────────────┐
│ React 18 + TypeScript + Tailwind SPA (Vite build) — PLASTEX-style │
│  navy sidebar, blue/white workspace, Lucide icons, responsive     │
└──────────────▲────────────────────────────────────────────────────┘
               │ HTTPS · JSON · Sanctum token · X-Company context
┌──────────────┴────────────────────────────────────────────────────┐
│ Nginx — TLS, static SPA, /api reverse proxy, security headers,      │
│         protected document routes (no public PDF URLs)              │
└──────────────▲────────────────────────────────────────────────────┘
               │ FastCGI
┌──────────────┴────────────────────────────────────────────────────┐
│ PHP-FPM · Laravel API (multi-tenant modular monolith)              │
│                                                                    │
│ HTTP:   Controllers → FormRequests → [Tenant scope + Module gate   │
│         + Policy authZ] → API Resources                            │
│                                                                    │
│ Domain modules: Organization · Security · CompanyConfig(BizType/   │
│   Industry) · MasterData · Inventory · Manufacturing · Purchase     │
│   (Local/Import + LandedCost) · Sales (Local/Export + FX) ·         │
│   Accounting · Vat(+Mushak 9.1 Note engine) · Vds · JobWork ·       │
│   FixedAssets · Mis · Reporting · DocumentCenter · Backup · Approval │
│                                                                    │
│ Shared kernel: Posting engine (double-entry) · Stock engine ·       │
│   Numbering · Audit · Money/FX · Document/PDF/XLSX engine · Approval │
└──────▲─────────────────────▲──────────────────────▲────────────────┘
       │                     │                      │
┌──────┴──────┐      ┌───────┴───────┐     ┌────────┴────────┐
│ MySQL 8     │      │ Redis 7       │     │ Local disk       │
│ InnoDB,     │      │ queue/cache/  │     │ (uploads, PDFs,  │
│ utf8mb4     │      │ session/locks │     │  backup staging) │
└─────────────┘      └───────────────┘     └────────┬─────────┘
                                                    │ Drive API (least-privilege)
                                           ┌────────┴─────────┐
                                           │ Google Drive      │
                                           │ (encrypted backups)│
                                           └───────────────────┘
```

### 1.2 Stack & versions (pinned at init, recorded in a compatibility matrix)
| Layer | Choice | Target | Note |
|---|---|---|---|
| OS | Ubuntu Server LTS | 24.04 | portable to VPS |
| PHP | PHP-FPM | 8.2 (dev) / 8.3 (prod) | Laravel 12 compatible |
| Backend | Laravel (REST API) | 12.x (scaffolded) | |
| Auth | Laravel Sanctum | bundled | SPA + token; §1.4 |
| DB | MySQL / InnoDB | 8.0.x, utf8mb4, strict | row locking |
| Cache/Queue/Lock | Redis | 7.x | |
| Frontend | React + TypeScript | 19 / 6.x (scaffolded) | |
| Build / Styling | Vite 8 / **Tailwind CSS** 4.x | — | spec-mandated Tailwind |
| UI components | **shadcn/ui** (Radix + Tailwind) + Lucide | — | matches navy/blue PLASTEX look |
| FE server-state | TanStack Query + Axios | current | |
| PDF render | **Browsershot (headless Chromium)** *or* dompdf | — | decision §15.3 |
| PDF preview | PDF.js | current | in-app viewer |
| Excel | `maatwebsite/excel` (PhpSpreadsheet) | current | XLSX |
| RBAC | `spatie/laravel-permission` | current | per-action perms |
| Tests | Pest + Vitest/RTL + Playwright | current | |
| Web server | Nginx | distro stable | |
| VCS | Git monorepo | — | `/backend /frontend /others` |

### 1.3 Repository layout
```
irlvat/
├─ backend/  (Laravel)
│  ├─ app/
│  │  ├─ Modules/{Organization, Security, CompanyConfig, MasterData,
│  │  │           Inventory, Manufacturing, Purchase, Sales, Accounting,
│  │  │           Vat, Vds, JobWork, FixedAssets, Mis, Reporting,
│  │  │           DocumentCenter, Backup, Approval}/
│  │  │     each: Models/ Services/ Http/{Controllers,Requests,Resources}/
│  │  │           Policies/ Migrations/ Tests/
│  │  └─ Shared/ {Tenancy, Money, Fx, Numbering, Audit, Posting, Stock,
│  │             Document, Enums}
│  ├─ database/{migrations,seeders,factories}/
│  ├─ tests/{Feature,Unit}/   └─ openapi/
├─ frontend/ (React+TS+Vite+Tailwind)
│  └─ src/{modules, shared, api-client, routes, layout}/
├─ deploy/ (install/deploy scripts, nginx, php, mysql, systemd, backup, .env samples)
├─ docs/  (this file, ERD.md, data-dictionary.md, PROJECT_STATUS.md, runbook.md)
└─ README.md
```
**Boundary rule:** a module never writes another module's tables directly — it calls
that module's service (e.g. Sales → `Accounting\PostingService`, `Vat\ClassifierService`).

### 1.4 Tenancy, auth & authorization (three gates on every protected request)
1. **Tenant scope** — resolve current `company_id` (from the authenticated user's
   company binding + an `X-Company` header validated against allowed companies). A
   global Eloquent scope filters every tenant model; writes stamp `company_id`.
2. **Module gate** — middleware checks the resolved company has the target module
   **enabled for its business type** (`company_modules`). Enforced in the API, not just
   by hiding menus.
3. **Policy authZ** — Sanctum authenticates; `spatie` roles/permissions authorize the
   specific action (`purchase.import.po.approve`, `vat.return.submit`, …); plus
   **factory/warehouse scope** so a scoped user can't touch other locations.
- Passwords hashed (bcrypt/argon2); **NID encrypted at rest** + blind-index for lookup;
  profile photos validated & stored privately; audit log on sensitive changes; users
  **deactivated, never deleted** when referenced by transactions; optional TOTP MFA
  behind a flag.

---

## 2. Complete Module Dependency Map

Layered, acyclic, bottom-up build order. `A → B` = "A depends on / calls B".

```
L5 read-only   ┌───────────────────────────────────────────────────────┐
               │ MIS / Dashboards / Reporting / Document Center         │ reads all,
               └───────────────▲───────────────────────────────────────┘ posts nothing
L4 statutory/   ┌──────────────┼──────────────┬───────────────┐         (except export jobs)
   overlay      │     VAT       │     VDS      │   Job Work    │
               │ (+Mushak 9.1  │              │ (customer-    │
               │  Note engine) │              │  owned)       │
               └──────▲────────┴──────▲───────┴──────▲────────┘
L3 transactional ┌────┴──────┬────────┴──────┬───────┴───────┬──────────┐
   business      │ Purchase  │    Sales      │ Manufacturing │  Fixed    │
               │ Local/    │  Local/Export │ (BOM, orders, │  Assets   │
               │ Import +  │  + FX         │ WIP, scrap,   │           │
               │ LandedCost│               │ costing)      │           │
               └────▲──────┴───────▲───────┴──────▲────────┴────▲──────┘
L2 shared      ┌────┴──────────────┴──────────────┴─────────────┴──────┐
   engines     │ Accounting(Posting) ◄──► Inventory(Stock)             │
               │ Numbering · Audit · Money/FX · Document(PDF/XLSX) ·    │
               │ Approval workflow                                     │
               └────▲──────────────────────────────────────────▲──────┘
L1 foundation  ┌────┴──────────────────┐   ┌────────────────────┴──────┐
               │ CompanyConfig          │   │ MasterData                 │
               │ (BizType, Industry,    │   │ (products/SKU, UoM+conv,   │
               │  module enablement,    │   │  categories, attributes,   │
               │  numbering cfg, FY,    │   │  tax masters)              │
               │  currencies)           │   │                            │
               └────▲───────────────────┘   └─────────────▲──────────────┘
L0 core        ┌────┴──────────────┐  ┌──────────────┐  ┌─┴──────────────┐
               │ Organization      │  │ Security      │  │ Backup /        │
               │ (company, factory,│  │ (users, RBAC, │  │ System Health   │
               │  warehouse, loc)  │  │  tenant scope)│  │ (no deps)       │
               └───────────────────┘  └───────────────┘  └─────────────────┘
```
**Rules:** Accounting + Inventory are the shared engines everything posts through.
VAT/VDS *observe* source transactions and produce registers/documents (read, don't
mutate source). Job Work reuses Inventory/Manufacturing/Accounting/VAT but keeps
customer-owned stock strictly separate. MIS/Reporting is read-only. **Build order =
bottom-up** (L0→L5), see §14. **No HR/Payroll node exists.**

---

## 3. Initial ERD (domain overview)

Full table-by-table ERD + data dictionary are separate deliverables (`docs/ERD.md`,
`docs/data-dictionary.md`). Conventions: **every tenant table has `company_id` (FK,
indexed, in composite uniques)**; money `DECIMAL(18,4)`, qty `DECIMAL(18,4)`, rates
`DECIMAL(18,6)`, FX rate `DECIMAL(18,8)`; utf8mb4; InnoDB; FKs on; `created_by/at`,
`updated_by/at`; soft-delete only on master data, **never on posted transactions**.

### 3.1 Organization, CompanyConfig & Security
```
companies ─1─┬─< factories ─1─< warehouses ─1─< storage_locations
             ├─< financial_years ─1─< accounting_periods
             ├─< currencies, exchange_rates (date, from_ccy, to_ccy, rate)
             ├─< company_business_types (primary + enabled flags)
             ├─< company_modules (module_key, enabled)      ← module gate source
             ├─< industry_templates / company_industry_config
             ├─< number_sequences, approval_workflows, system_settings
users ─< user_company_access >─ companies     ← tenant binding
users ─< user_factory_access  >─ factories
users ─< model_has_roles >─ roles ─< role_has_permissions >─ permissions
login_histories, audit_logs (polymorphic, before/after JSON), password_histories
```
`users`: full_name, mobile, email(unique), username(unique), password(hash),
**nid_encrypted + nid_index**, photo_path, address, status, created_by, last_login_at.
(Employee-ID style fields allowed; **no HR module**.)

### 3.2 MasterData, Inventory & Batches
```
products (SKU, category, type: raw/fg/pkg/consumable/spare, attributes JSON, uom)
product_units (base + alt), unit_conversions, product_categories, product_attributes
batches (lot_no, mfg/expiry, owner_type, owner_id)
stock_balances (company, product, warehouse, batch, owner) qty,value  [UNIQUE]
stock_movements (append-only: company, product, wh, batch, owner, qty±, unit_cost,
                 value±, source_type, source_id, posted_at)
stock_adjustments, stock_transfers, stock_counts ─< lines, opening_stocks
```
Stock changes go only through the **Stock engine** (§7) in a locked transaction.
`owner_type ∈ {company, customer}` — customer-owned never in company valuation/GL.

### 3.3 Accounting (shared)
```
chart_of_accounts (tree; asset/liability/equity/income/expense)
journal_entries (company, date, period_id, source_type, source_id, status,
                 reversal_of_id, UNIQUE(company,source_type,source_id,purpose))
journal_lines (entry_id, account_id, cost_center_id, ccy, fx_rate, debit, credit)
posting_rules_map (configurable source→accounts), cost_centers, budgets ─< lines
bank_accounts, cash_books, bank_reconciliations, fund_transfers
```
Posting: server-side, transactional, **balanced** (Σdebit=Σcredit), **idempotent**,
reversal-only after posting, period-lock aware.

### 3.4 Purchase (Local / Import / Service) + Landed Cost
```
suppliers ─< purchase_requisitions, supplier_quotations
          ─< purchase_orders ─< po_lines            (local|import|service)
          ─< goods_receipt_notes ─< grn_lines
          ─< purchase_invoices (material|service) ─< pi_lines
          ─< purchase_returns, supplier_payments, supplier_adjustments
          ─< supplier_ledger(view)
-- Import extension --
import_shipments (po_id, incoterm, ccy, exch_rate, status)
  ─< proforma_invoices, supplier_contracts, lc_tt_tracking
  ─< commercial_invoices, packing_lists, bills_of_lading
  ─< customs_bill_of_entry, customs_clearances
import_charges (type: freight/insurance/customs_duty/port/cnf/other, ccy, amount,
                recoverable_flag)
landed_cost_runs ─< landed_cost_allocations (charge, product, basis, allocated_amt)
import_cost_reconciliations
```

### 3.5 Sales (Local / Export) + FX
```
customers ─< quotations, sales_orders ─< so_lines
          ─< sales_invoices ─< si_lines
          ─< delivery_challans ─< dc_lines     (separate doc; partial deliveries)
          ─< customer_receipts, sales_returns, credit_notes, pricing_rules
          ─< customer_ledger(view)
-- Export extension --
export_contracts, export_orders ─< export_shipments (multi-shipment per order)
  ─< proforma_invoices, commercial_invoices, packing_lists
  ─< exp_forms, shipping_bills, bills_of_lading, certificates_of_origin
export_receivables, export_realizations (exchange gain/loss), export_costing
```

### 3.6 Manufacturing
```
boms (product, version, effective_from/to, status) ─< bom_lines (input, coeff)
work_centers ─< machines ; molds_tools ; shifts
production_orders ─< operations ; material_issues ─< lines (stock out)
production_receipts ─< lines (WIP/FG in) ; scrap_records, rework_records
machine_downtime, quality_inspections, production_costs, production_variances
mrp_runs (material requirement planning)
```

### 3.7 VAT / Mushak 9.1 / VDS  (see §8, §9)
```
vat_rates, vat_transaction_types, vat_tax_treatments
vat_return_notes (9.1 Note Numbers — data, verified), vat_subform_mappings
vat_note_mappings (classification → note), vat_rule_versions
vat_transaction_classifications (per invoice line, frozen rule_version)
vat_return_lines, vat_mapping_approvals
vat_sales_register, vat_purchase_register, vat_input_output
mushak_documents (form, template_version, status lifecycle), mushak_templates
vds_rules, vds_deductions ─< vds_certificates ─< vds_treasury_deposits
vds_payable_ledger
```

### 3.8 Job Work (customer-owned) + Fixed Assets + System
```
manufacturing_contracts ─< contract_products(rate) ─< contract_terms
job_work_orders ─< operations ; customer_material_receipts ─< lines (owner=customer)
customer_material_lots/issues/consumption/returns, customer_owned_fg
job_work_deliveries, processing_invoices (service charge, not goods sale)
material_reconciliations, job_work_costing
asset_categories, fixed_assets ─< depreciation_schedules ─< depreciation_entries
asset_acquisitions/transfers/revaluations/disposals/maintenance
export_jobs, report_definitions, backup_jobs ─< backup_logs
```

---

## 4. Company Type & Industry Configuration Design

The single most important differentiator: **one application, configured per company** —
never separate apps or menu-hiding alone.

### 4.1 Model
- `companies.primary_business_type ∈ {manufacturing, service, trading}`
- `company_business_types` — enabled additional types (hybrid: Mfg+Trading,
  Mfg+Service, Trading+Service, all three).
- `industry_templates` — reusable presets (e.g. *Plastic Manufacturing*: BOM, machines,
  molds, production orders, scrap, yield, process costing; *Service*: service catalogue,
  projects, work orders, billable expenses, recurring billing; *Trading*: procurement,
  inventory, distribution, sales).
- `company_modules(company_id, module_key, enabled)` — the **authoritative source** for
  the module gate (§1.4). Seeded from the chosen business types + industry template, then
  adjustable.
- `company_industry_config` — industry-specific attributes, workflows, document sets.

### 4.2 Enforcement (defense in depth)
1. **Backend module gate** middleware rejects any request to a module not enabled for the
   company (403) — the real enforcement.
2. **Business services** check capability (e.g. production endpoints refuse if
   manufacturing not enabled).
3. **Frontend** reads an enabled-modules manifest to render only permitted sidebar items
   — convenience, *not* security.

### 4.3 Company Setup Wizard (flow)
`Company profile & tax IDs → currencies/FY → business type(s) → industry template →
factories/warehouses → document numbering → VAT config → approval workflows → reporting
settings → module review & activate`. Produces the `company_modules` + config rows.

---

## 5. Local / Import Purchase Workflow

### 5.1 Local
`Requisition → Supplier Quotation → PO (approval) → GRN (material → stock in) →
Purchase Invoice (material|service) → Supplier Bill → Payment → (Return / Adjustment)`.
- **Material GRN** posts a stock movement + GL (Dr Inventory / Cr GRN-clearing);
  PI matches clearing ↔ payable.
- **Service purchase**: no stock movement; Dr Expense/Asset, Cr Payable.
- Local transport/additional charges captured; supplier ledger & aging; VDS hook (§9);
  VAT classification hook (§8).

### 5.2 Import
`Import Requisition → Import PO → Proforma → Supplier Contract → LC/TT tracking (FX) →
Commercial Invoice + Packing List + BL/AWB → Shipment tracking → Customs Bill of Entry →
Clearance (duty/taxes) → Import GRN (stock in at landed cost) → Payment →
Import Cost Reconciliation`.
- **Foreign currency** throughout: each doc stores ccy + exchange rate at the relevant
  date; FX differences posted to exchange gain/loss.

### 5.3 Landed Cost engine
- Collect eligible `import_charges` (freight, insurance, customs duty, port, C&F, other),
  each flagged **recoverable vs non-recoverable** (recoverable e.g. input-VAT-type taxes
  do **not** capitalize; non-recoverable duties **do** form inventory cost — per verified
  tax/accounting rules, configurable).
- Allocate across products by a configured **basis**: quantity / weight / volume /
  product value / justified combination.
- Store original cost, each additional cost, basis, allocated amount, resulting inventory
  value — full audit. **A charge is never allocated twice** (idempotent run keyed by
  shipment+charge).
- Final landed unit cost feeds the Stock engine valuation on Import GRN.

---

## 6. Local / Export Sales Workflow

### 6.1 Local
`Quotation → Sales Order (approval) → Sales Invoice → Delivery Challan → (partial
deliveries) → Customer Receipt → (Sales Return / Credit Note)`.
- **Invoice ≠ challan** (separate documents/tables); each dispatch event gets its own
  challan; the same product supports multiple partial-dispatch challans.
- Pricing/discount rules; optional sales commission (flag); customer ledger & aging;
  VAT classification + Mushak doc hooks (§8); margin.

### 6.2 Export
`Export Quotation → Export Contract → Export Order → Proforma → Commercial Invoice +
Packing List → (Export LC/TT, incoterms, destination) → multi-shipment dispatch →
Export customs docs (EXP form, shipping bill, BL/AWB, Certificate of Origin) →
Export Receivable → Payment realization (exchange gain/loss) → Export costing/profit`.
- **One export order → many shipments** (partial shipment).
- Foreign currency receivable; realization tracking; exchange gain/loss posting.
- Statutory export docs + VAT treatment are **configured** per verified current rules
  (zero-rated/export treatment entered & approved, not assumed).

---

## 7. Inventory & Accounting Transaction Design

Two shared engines, each running **inside one DB transaction** per business event with
`SELECT … FOR UPDATE` locks, idempotency keys, and period checks.

### 7.1 Posting engine (double-entry)
```
PostingService::post(JournalEntryDraft): JournalEntry
  guard period open · idempotency key (company+source_type+source_id+purpose) unused
  build lines from configurable posting_rules_map
  assert Σdebit == Σcredit (to the paisa; multi-currency recorded with fx_rate,
     base-currency amounts balance)
  DB::transaction { insert entry+lines (locked); update ledger balances }
  duplicate key → return existing entry (no double post)
reverse(entry, reason) → mirror entry linked by reversal_of_id; original never mutated
```
Source→posting examples (accounts configurable, not hard-coded numbers):
Material GRN → Dr Inventory / Cr GRN-clearing · Import GRN → Dr Inventory(landed) /
Cr clearing + duty payables · Sales invoice → Dr AR / Cr Revenue / Cr Output-VAT (if
applicable) · Production receipt → Dr FG / Cr WIP (at cost) · Job work processing
invoice → Dr AR / Cr Processing-service income / Cr Output-VAT (**no COGS for
customer-owned material**) · FX realization → Dr/Cr Exchange gain/loss.

### 7.2 Stock engine
```
StockService::move(lines[]):
  DB::transaction { foreach line:
    lock stock_balances (company,product,wh,batch,owner) FOR UPDATE
    if outgoing and (qty - out) < 0 and !allow_negative → throw
    apply qty±, recompute value± per costing method
    append immutable stock_movements; upsert stock_balances }
```
- **Costing:** configurable **weighted-average (default)** or **FIFO** per category,
  recorded & justified; feeds Inventory→GL reconciliation.
- **Concurrency-safe** (tested, §14): parallel issues never oversell or corrupt balances.
- **Owner separation:** `owner=customer:{id}` excluded from company valuation/GL.
- Posted stock movements are **never silently deleted** — adjustments/reversals only.

### 7.3 Why single shared engines
One posting engine + one stock engine = one place enforcing balancing, locking,
idempotency, period control, audit, and Inventory↔GL reconciliation. Satisfies the
"shared accounting tables across all modules" requirement.

---

## 8. Mushak 9.1 Note Number & Subform Mapping Design  ⭐ (critical)

**Principle:** classification is **data-driven and verified**, never hard-coded, never
guessed. VAT rate alone does **not** determine the Note Number — treatment matters.

### 8.1 Classifier inputs (per invoice *line*)
transaction type · {Local/Import/Service} purchase or {Local/Export} sales · product/
service category · VAT rate · taxable amount · input-VAT treatment · output-VAT
treatment · exemption/zero-rated flag · applicable tax rule · effective date.

### 8.2 Tables (normalized)
`vat_rates` · `vat_transaction_types` · `vat_tax_treatments` · `vat_return_notes`
(the 9.1 Note Numbers — **verified data**) · `vat_note_mappings` (classification tuple →
Note Number) · `vat_subform_mappings` (Note → subform/schedule) · `vat_rule_versions`
(effective-dated, legal_ref, approval/verification status, verified_by) ·
`vat_transaction_classifications` (result per line, **frozen rule_version** for audit) ·
`vat_return_lines` · `vat_mapping_approvals`.

### 8.3 Behavior
1. Classify each eligible invoice line against configured rules for its effective date.
2. Determine the applicable tax treatment.
3. Assign the **verified** Note Number (never invented).
4. Map classified amount & tax to the correct return line + subform/schedule.
5. Aggregate into Mushak 9.1 by configured rules.
6. **Drill-down** from each return line → source transactions.
7. **Reconcile** return totals ↔ accounting ↔ VAT registers.
8. **Prevent duplicate** inclusion in a return.
9. Preserve source classification + rule version (audit).
10. Corrections only via controlled adjustment / return-revision workflow.

### 8.4 Guardrails
- Missing/unverified mapping → transaction flagged **"Unmapped / Review Required"**;
  surfaced in the *Unmapped Transaction Report*; **never** auto-classified into a guessed
  line.
- Note→subform relationship is **data**, not code.
- The actual Note Number list + legal mappings must be entered from the **current
  official Mushak 9.1 form + instructions** and **approved by a practitioner** before use.
- Full VAT engine also provides: VAT Dashboard, rate/transaction-type/treatment masters,
  Input/Output & Purchase/Sales registers, return preparation & reconciliation, Mushak
  Document Register + Print Center, submission tracking, rule versioning, mapping
  approval, VAT compliance MIS.

---

## 9. VDS Design

Same philosophy — everything configurable, versioned, verified.
`VDS rule config → rate management (effective-dated) → transaction applicability →
supplier tax info → deduction → VDS register → certificate (generate/track) → treasury
deposit (refs) → VDS payable ledger → supplier VDS statement → deducted-vs-deposited
reconciliation → reports → accounting + VAT-return integration`.
Rates, service categories, deduction rules, deadlines, certificate/deposit procedures are
**configurable & legally verified**; **no hard-coded unverified rates.**

---

## 10. Customer-owned Material / Job Work Design

**Invariant:** customer-owned material is never company inventory, never a company asset,
never auto-becomes a company sale.

- Every stock row/movement/batch carries **owner** (`company` | `customer:{id}`); company
  valuation, Inventory→GL, and balance sheet include **only** `owner=company`.
- Customer Material Receipt → stock movement `owner=customer`, **no GL asset, no purchase**
  — a *custody* balance, tracked by customer/contract/factory/warehouse/lot/qty.
- Lifecycle: `Contract (products, process rates, scrap ownership, terms) → Job Work Order
  → Customer Material Receipt → Issue/Consumption (owner stays customer) → Production run
  → Customer-owned FG (still customer) → Scrap (per verified contract) → Job Work Delivery
  → Processing Invoice (service charge + VAT per verified rule, NOT goods sale) → Material
  Reconciliation (received = consumed + returned + scrap + loss) → Job work costing`.
- Ownership transfer (if ever) is an explicit, audited workflow — never implicit.
- Customer-owned scrap does not become company property without a valid transfer.
- Material reconciliation is mandatory and **tested** (§14).

---

## 11. MIS & Reporting Architecture

- **Read-only** over all modules via dedicated query/read services (no business posting).
- **Reusable Report Engine**: one pipeline (definition → data query → render) feeding both
  **PDF** (server-rendered) and **XLSX** (`maatwebsite/excel`), with company logo,
  headers/totals, date/number formats, pagination, report metadata, configurable Mushak
  layouts.
- Every report: in-app **PDF.js preview + Full Screen + open-in-new-tab + browser print +
  download + XLSX export**, with filters (date range, factory, warehouse, product,
  customer, supplier, status, currency, country) and **drill-down to source transactions**.
- **Security:** report/document endpoints are authenticated + authorized + tenant-scoped;
  generated files are **never served by public/predictable URLs** (signed, authZ-checked
  streaming route); role-based report access tested.
- Dashboards: Executive, Purchase (local/import, landed cost, shipment status), Sales
  (local/export, country/currency, realization), Manufacturing (plan vs actual, yield,
  scrap, downtime, variance, costing), Inventory (ledger, aging, valuation, reorder,
  batch trace, customer-owned balance), Accounting (TB, P&L, BS, cash flow, GL, aging,
  budget vs actual), VAT/VDS (Mushak 9.1 note-wise & subform-wise, registers, input/
  output, **unmapped transactions**, VDS reconciliation, submission status), Job Work
  (material received/consumed/returned, FG custody, pending delivery, charges, profit).
- **Document Center & Exports**: central registry of generated PDFs/XLSX + export jobs.

---

## 12. Google Drive Backup & Recovery Design

**Golden rule in code:** a backup counts as successful only after the remote upload is
**checksum-verified**; never delete the last good backup because a new one failed.

```
Scheduler every 20 min → BackupJob (queued, singleton lock):
 1 mysqldump --single-transaction (InnoDB-consistent)
 2 bundle essential uploads + generated documents (when needed for full recovery)
 3 gzip compress
 4 encrypt (AES-256; key stored SEPARATELY — not in repo, not on Drive)
 5 sha256 checksum
 6 upload to a dedicated Drive folder via Drive API (least-privilege; §15.3 auth choice)
 7 verify remote metadata/size + checksum
 8 record backup_jobs (success only if 7 passes)
 9 retention: keep 30 days (~2,160 points); delete expired ONLY after confirming a newer
   verified backup exists; log every cleanup
10 alerts on failure / auth expiry / Drive quota near-limit
```
- Logs every attempt/upload/failure/retry/cleanup/restore (`backup_logs`).
- **Restore scripts** + periodic automated **restore test** into a scratch DB (tested §14).
- Optional **full + binlog/incremental** strategy for large DBs.
- **Documented limitation:** single-PC deployment has downtime if the PC fails; Drive
  backup provides recoverability, **not** automatic failover to a live server.

---

## 13. Linux Deployment Plan

Target **Ubuntu Server 24.04 LTS**; portable to another PC/VPS via documented steps.
**Native install recommended** for a single box; Docker Compose offered as alternative
(persistent volumes, health checks, secret handling, backup/restore) — decision §15.3.

`/deploy` provides: version matrix · `install.sh` (PHP 8.3 + ext, MySQL 8, Redis, Nginx,
Node 20 for build, Composer) · `nginx/` (TLS, SPA, `/api` FastCGI, security headers,
protected document routes) · `php/` tuning · `mysql/secure-setup.sql` (least-privilege
app user, strict mode) · `.env.example` (backend + frontend) · `systemd/` (queue worker + scheduler
via cron/timer) · `deploy.sh` (install→migrate→build→cache→reload, with **rollback**) ·
`backup/` + `restore/` · logrotate · ufw firewall · certbot HTTPS · `docs/runbook.md`
(install, upgrade, rollback, backup, restore, troubleshooting). No machine-specific
absolute paths in code; move procedure = provision → `install.sh` → restore verified
backup → set env → deploy → verify checklist.

---

## 14. Development Milestones (phased, bottom-up)

Each phase ends with: code + migrations + seeders + tests green + OpenAPI updated +
manual-verification notes + `PROJECT_STATUS.md` updated. No phase is "done" without
passing tests.

| Phase | Scope | Exit criteria |
|---|---|---|
| **0** | This architecture + ERD + data dictionary + decisions locked | You approve |
| **1** | Skeleton: Laravel+React+Vite+Tailwind, CI, health check, OpenAPI scaffold, `PROJECT_STATUS.md` | App boots, `/api/health` green, CI runs tests |
| **2** | Organization + Security + **multi-tenant isolation** (company_id scope, user→company binding, RBAC, factory scope, NID encryption, audit, numbering, MFA flag) | Tenant-isolation + authZ + access-boundary tests pass |
| **3** | **CompanyConfig**: Setup Wizard, business-type engine, industry templates, **module gate** middleware | Module-enablement + gate tests pass |
| **4** | **Shared engines**: Posting (CoA, periods, multi-currency) + Stock (costing) | Double-entry, idempotency, concurrency, period-lock tests pass |
| **5** | MasterData + Inventory ops (products/SKU/UoM/conv/categories/batches, opening stock, GRN, issue, transfer, count, adjustment, valuation, ledger, reorder/aging) | Stock↔GL reconciliation test passes |
| **6** | **Purchase Local** (requisition→PO→GRN→invoice→payment→return, supplier ledger/aging, material vs service, VDS+VAT hooks) | Local purchase + stock/GL tests pass |
| **7** | **Purchase Import + Landed Cost** (shipment, FX, LC/TT, customs, import GRN, landed-cost allocation, reconciliation) | Landed-cost allocation + FX tests pass |
| **8** | **Sales Local** (quote→SO→invoice→**challan**→receipt→return/credit, pricing, aging, margin) | Invoice≠challan + partial-dispatch tests pass |
| **9** | **Sales Export + FX** (contract→order→multi-shipment→commercial docs→realization→exchange gain/loss→costing) | Multi-shipment + FX realization tests pass |
| **10** | **Manufacturing** (BOM+versioning+coeff, MRP, orders, machines/molds/shifts, consumption, WIP, FG receipt, scrap/rework, QC, yield, variance, costing, batch trace) | Consumption/yield/variance tests pass |
| **11** | **Accounting completion** (approvals, cash/bank books, receipts/payments, fund transfer, bank rec, cost centers, budget, TB/P&L/BS/cash flow, year-close, period lock) | Financial statements + reconciliation tests pass |
| **12** | **Fixed Assets** (categories, register, acquisition/transfer/revaluation/disposal/maintenance, depreciation → GL) | Depreciation posting tests pass |
| **13** | **VAT/Mushak + Mushak 9.1 Note engine** (masters, classifier, note/subform mapping, registers, return prep, document register/print, unmapped report, reconciliation) — rules entered **unverified, pending practitioner review** | Classifier + note-mapping + unmapped + reconciliation tests pass |
| **14** | **VDS** (rules, deduction, register, certificate, treasury, payable ledger, reconciliation, reports) | Deducted↔deposited reconciliation tests pass |
| **15** | **Job Work** (contracts, customer-owned custody, receipts/issues/returns, customer FG, processing invoice, reconciliation, costing) | Custody separation + material reconciliation tests pass |
| **16** | **Reporting engine** (PDF preview/full-screen/new-tab/print/download + XLSX, secured document routes, Document Center) | Report security + output tests pass |
| **17** | **MIS dashboards** (all, with filters + drill-down) + Approval Center | Drill-down + filter + role-based access tests pass |
| **18** | **Google Drive backup/restore** (20-min, encrypt, verify, 30-day retention, alerts, restore test) + System Health | Upload-verify + retention + restore tests pass |
| **19** | Hardening: security pass, rate limiting, headers, E2E acceptance (Playwright), deployment + production-readiness checklists, docs complete | Acceptance suite green; checklists signed off |

VAT/VDS/JobWork (13–15) sit on the posting+stock+config engines by design (§2).

---

## 15. Risks, Assumptions & Acceptance Criteria

### 15.1 Risk register (top items)
| # | Risk | Severity | Mitigation |
|---|---|---|---|
| R1 | **VAT/Mushak 9.1 Note mapping / VDS / customs rules wrong or outdated** | **Critical** | Rules are versioned, effective-dated, verified data; unmapped→Review Required; never invented; practitioner approval gate. |
| R2 | **Cross-company data leak** (tenant isolation failure) | **Critical** | `company_id` global scope + policy + module gate; tenant-isolation tests on every endpoint. |
| R3 | Customer-owned material treated as company asset/sale | Critical | Owner-partitioned ledger; excluded from valuation/GL; mandatory reconciliation + tests. |
| R4 | Concurrency → negative/inconsistent stock or double posting | High | Single transactional engines, row locks, idempotency keys, concurrency tests. |
| R5 | Landed cost / FX errors → wrong inventory value & margins | High | Idempotent allocation (no double charge), recoverable/non-recoverable split, FX gain/loss posting, tests. |
| R6 | Backup appears to work but isn't restorable | High | Checksum-verified upload; periodic restore test; never delete last good backup. |
| R7 | Sensitive data (NID, passwords, keys) leakage | High | Hashing, encryption at rest, separate key store, restricted access, private document routes, audit. |
| R8 | Very large scope / long timeline | High | Strict bottom-up phasing; each phase shippable & tested; `PROJECT_STATUS.md` continuity. |
| R9 | Single-server downtime | Medium | Documented; backup ≠ HA; standby a future option. |

### 15.2 Key assumptions (please confirm)
- A qualified Bangladesh VAT/tax practitioner supplies & approves all VAT/Mushak/9.1
  Note/VDS/customs rules, rates, thresholds, Note Numbers, note→subform mappings, and
  form templates. **Nothing statutory ships active & unverified.**
- **Multi-currency is required** (import/export): base currency per company (default BDT)
  + foreign-currency transactions with effective-dated exchange rates and gain/loss.
- Multi-company tenant isolation required from day one.
- Single production server initially; Google Drive for backup; no HA requirement yet.
- Decimal precision: money/qty `(18,4)`, rates `(18,6)`, FX `(18,8)` — confirm acceptable.
- **HR & Payroll excluded** (confirmed by spec).

### 15.3 Open decisions I need from you (Phase-1 blockers)
1. **PDF renderer:** headless-Chromium (**Browsershot** — pixel-perfect for official
   Mushak forms, needs Chromium on server) *vs* dompdf (lighter, limited CSS).
   Recommend **Browsershot** — OK to install Chromium on the server?
2. **Deployment:** **native install** (recommended) *vs* Docker Compose?
3. **Google Drive auth:** **service account** (unattended 20-min job — recommended) *vs*
   user OAuth. Which Drive account/folder will own backups?
4. **Tenancy scope:** will one login ever need to switch between multiple companies, or is
   each user bound to exactly one company? (Affects the company-context mechanism.)
5. **Base currency / UI language:** BDT + English UI for v1, with multi-currency
   transactions — or is **Bangla UI** needed in v1 too?
6. **Industry templates at launch:** build only the **Plastic Manufacturing** template
   first (others stubbed/configurable later), or seed Service + Trading templates in v1 as
   well?

(UI kit and RBAC are now settled by the spec: **Tailwind + shadcn/ui + Lucide**, and
**spatie/laravel-permission** — say if you'd prefer otherwise.)

### 15.4 Acceptance criteria (system-level definition of done)
- Multi-tenant: no endpoint returns another company's data; module gate enforced in API.
- Business-type config decides enabled modules in the backend, not just menus.
- Accounting: posted entries balance; no edit/delete of posted (reversals only); period
  locking; Inventory↔GL reconciles; multi-currency balances in base currency.
- Inventory: concurrency-safe; no negative stock unless allowed; full ledger; customer-
  owned never in company valuation/GL.
- Purchase: material vs service correct; **import landed cost** allocated once, FX
  handled; recoverable vs capitalized taxes correct.
- Sales: invoice≠challan; partial deliveries; **export** multi-shipment + realization +
  exchange gain/loss.
- VAT: Mushak 9.1 classifier assigns only **verified** Note Numbers; unmapped→Review
  Required (never guessed); registers reconcile to GL; nothing statutory hard-coded.
- VDS: deducted↔deposited reconciles; rules versioned & verified.
- Job work: customer material never in company valuation/GL; reconciliation balances;
  processing invoices charge service, not goods.
- Reporting: every report → PDF preview + full-screen + new-tab + print + download + XLSX;
  private files not exposed by URL; role-based access enforced.
- Backup: 20-min; encrypted; upload checksum-verified; 30-day retention; never deletes
  last good backup; restore test passes; alerts fire.
- Deployment: fresh-Ubuntu install from scripts; documented portability; rollback works.
- No HR/Payroll module exists. No fake links; no "deployed" claims without evidence; no
  placeholder passing as real functionality.

---

## Next step
On your approval of this architecture — and your answers to the **6 open decisions in
§15.3** — I will start **Phase 1 (project skeleton)**: Laravel 11 + React/Vite/Tailwind +
CI + health check + OpenAPI scaffold + `PROJECT_STATUS.md`, with the install/deploy base
and a first green test run. No implementation code is written until you say go.
