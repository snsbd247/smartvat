<?php

use App\Http\Controllers\System\HealthController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\MasterData\Http\Controllers\ProductController;
use App\Modules\Purchase\Http\Controllers\ImportController;
use App\Modules\Purchase\Http\Controllers\PurchaseController;
use App\Modules\Purchase\Http\Controllers\SupplierController;
use App\Modules\Sales\Http\Controllers\CustomerController;
use App\Modules\Sales\Http\Controllers\SalesController;
use App\Modules\Manufacturing\Http\Controllers\ProductionController;
use App\Modules\Accounting\Http\Controllers\AccountingController;
use App\Modules\FixedAssets\Http\Controllers\FixedAssetController;
use App\Modules\Vat\Http\Controllers\VatController;
use App\Modules\Vds\Http\Controllers\VdsController;
use App\Modules\JobWork\Http\Controllers\JobWorkController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\Approval\Http\Controllers\ApprovalController;
use App\Modules\Settings\Http\Controllers\SettingController;
use App\Modules\Document\Http\Controllers\DocumentController;
use App\Modules\Mis\Http\Controllers\MisController;
use App\Modules\Backup\Http\Controllers\BackupController;
use App\Modules\Organization\Http\Controllers\CompanyConfigController;
use App\Modules\Organization\Http\Controllers\CompanyController;
use App\Modules\Organization\Http\Controllers\FactoryController;
use App\Modules\Organization\Http\Controllers\WarehouseController;
use App\Modules\Security\Http\Controllers\AuthController;
use App\Modules\Security\Http\Controllers\SystemController;
use App\Modules\Security\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (prefix: /api)
|--------------------------------------------------------------------------
*/

// Public.
Route::get('/health', HealthController::class)->name('health');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1') // brute-force protection: 10 attempts/min/IP
    ->name('auth.login');

// Authenticated (any company the user belongs to).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');

    // Tenant-scoped: a company must be resolved and authorized for the user.
    Route::middleware('company')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::get('/company/current', [CompanyController::class, 'current'])->name('company.current');

        // Company configuration (business types, industry template, module manifest).
        Route::get('/company/modules', [CompanyConfigController::class, 'modules'])->name('company.modules');
        Route::get('/company/config', [CompanyConfigController::class, 'show'])->name('company.config.show');
        Route::put('/company/config', [CompanyConfigController::class, 'update'])->name('company.config.update');

        // Organization module (always enabled for every company).
        Route::apiResource('factories', FactoryController::class);

        // User management (always-on).
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('security/roles', [SystemController::class, 'roles'])->name('sec.roles');
        Route::get('security/permissions', [SystemController::class, 'permissions'])->name('sec.perms');
        Route::get('security/activity-logs', [SystemController::class, 'activityLogs'])->name('sec.activity');
        Route::get('security/login-history', [SystemController::class, 'loginHistory'])->name('sec.login');

        // Master data (always-on module).
        Route::apiResource('products', ProductController::class);

        // Purchase (always-on module): suppliers + procure-to-pay.
        Route::apiResource('suppliers', SupplierController::class);
        Route::post('purchase-orders', [PurchaseController::class, 'storeOrder'])->name('po.store');
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseController::class, 'approveOrder'])->name('po.approve');
        Route::post('goods-receipts', [PurchaseController::class, 'receiveGoods'])->name('grn.store');
        Route::post('purchase-invoices', [PurchaseController::class, 'postInvoice'])->name('pi.store');
        Route::post('supplier-payments', [PurchaseController::class, 'paySupplier'])->name('supplier.pay');
        Route::post('purchase-returns', [PurchaseController::class, 'returnGoods'])->name('pr.store');
        Route::get('suppliers/{supplier}/ledger', [PurchaseController::class, 'supplierLedger'])->name('supplier.ledger');

        // Import purchase + landed cost.
        Route::post('import-shipments', [ImportController::class, 'store'])->name('import.store');
        Route::get('import-shipments/{importShipment}/landed-cost', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('import-shipments/{importShipment}/receive', [ImportController::class, 'receive'])->name('import.receive');

        // Sales (always-on module): customers + local/export order-to-cash.
        Route::apiResource('customers', CustomerController::class);
        Route::get('sales-orders', [SalesController::class, 'orders'])->name('so.index');
        Route::post('sales-orders', [SalesController::class, 'storeOrder'])->name('so.store');
        Route::post('sales-orders/{salesOrder}/confirm', [SalesController::class, 'confirmOrder'])->name('so.confirm');
        Route::post('sales-orders/{salesOrder}/invoice', [SalesController::class, 'invoiceOrder'])->name('so.invoice');
        Route::post('sales-invoices', [SalesController::class, 'invoice'])->name('si.store');
        Route::post('delivery-challans', [SalesController::class, 'deliver'])->name('dc.store');
        Route::post('customer-receipts', [SalesController::class, 'receipt'])->name('customer.receipt');
        Route::post('sales-returns', [SalesController::class, 'salesReturn'])->name('sr.store');
        Route::get('customers/{customer}/ledger', [SalesController::class, 'customerLedger'])->name('customer.ledger');

        // Inventory module — gated: only companies with the inventory module enabled.
        Route::middleware('module:inventory')->group(function () {
            Route::apiResource('warehouses', WarehouseController::class)
                ->only(['index', 'store', 'show', 'destroy']);

            Route::post('inventory/receipts', [InventoryController::class, 'receipt'])->name('inventory.receipt');
            Route::post('inventory/issues', [InventoryController::class, 'issue'])->name('inventory.issue');
            Route::post('inventory/adjustments', [InventoryController::class, 'adjustment'])->name('inventory.adjustment');
            Route::post('inventory/transfers', [InventoryController::class, 'transfer'])->name('inventory.transfer');
            Route::get('inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
            Route::get('inventory/ledger', [InventoryController::class, 'ledger'])->name('inventory.ledger');
            Route::get('inventory/reconciliation', [InventoryController::class, 'reconciliation'])->name('inventory.reconciliation');
        });

        // Accounting (always-on module).
        Route::get('accounting/accounts', [AccountingController::class, 'accounts'])->name('acc.accounts');
        Route::post('accounting/accounts', [AccountingController::class, 'storeAccount'])->name('acc.accounts.store');
        Route::get('accounting/cost-centers', [AccountingController::class, 'costCenters'])->name('acc.cc');
        Route::post('accounting/cost-centers', [AccountingController::class, 'storeCostCenter'])->name('acc.cc.store');
        Route::post('accounting/journals', [AccountingController::class, 'storeJournal'])->name('acc.journal.store');
        Route::post('accounting/journals/{journalEntry}/reverse', [AccountingController::class, 'reverseJournal'])->name('acc.journal.reverse');
        Route::post('accounting/fund-transfers', [AccountingController::class, 'fundTransfer'])->name('acc.fund');
        Route::get('accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('acc.tb');
        Route::get('accounting/profit-loss', [AccountingController::class, 'profitAndLoss'])->name('acc.pl');
        Route::get('accounting/balance-sheet', [AccountingController::class, 'balanceSheet'])->name('acc.bs');
        Route::get('accounting/accounts/{account}/cash-book', [AccountingController::class, 'cashBook'])->name('acc.cashbook');
        Route::get('accounting/periods', [AccountingController::class, 'periods'])->name('acc.periods');
        Route::patch('accounting/periods/{period}/status', [AccountingController::class, 'setPeriodStatus'])->name('acc.period.status');
        Route::get('accounting/accounts/{account}/unreconciled', [AccountingController::class, 'bankUnreconciled'])->name('acc.bank.unrec');
        Route::post('accounting/bank-reconciliations', [AccountingController::class, 'bankReconcile'])->name('acc.bank.rec');
        Route::get('accounting/budgets', [AccountingController::class, 'budgets'])->name('acc.budgets');
        Route::post('accounting/budgets', [AccountingController::class, 'storeBudget'])->name('acc.budget.store');
        Route::get('accounting/budgets/{budget}/vs-actual', [AccountingController::class, 'budgetVsActual'])->name('acc.budget.va');
        Route::get('accounting/expenses', [AccountingController::class, 'expenses'])->name('acc.expenses');
        Route::post('accounting/expenses', [AccountingController::class, 'storeExpense'])->name('acc.expense.store');

        // Manufacturing — gated: only companies with the production module enabled.
        Route::middleware('module:production')->group(function () {
            Route::get('boms', [ProductionController::class, 'boms'])->name('bom.list');
            Route::get('production-orders', [ProductionController::class, 'orders'])->name('prod.list');
            Route::post('boms', [ProductionController::class, 'storeBom'])->name('bom.store');
            Route::post('production-orders', [ProductionController::class, 'storeOrder'])->name('prod.store');
            Route::post('production-orders/{productionOrder}/issue', [ProductionController::class, 'issue'])->name('prod.issue');
            Route::post('production-orders/{productionOrder}/receive', [ProductionController::class, 'receive'])->name('prod.receive');
        });

        // Fixed assets (always-on module).
        Route::get('fixed-assets', [FixedAssetController::class, 'index'])->name('fa.index');
        Route::get('asset-categories', [FixedAssetController::class, 'categories'])->name('fa.cat.list');
        Route::post('asset-categories', [FixedAssetController::class, 'storeCategory'])->name('fa.cat.store');
        Route::post('fixed-assets', [FixedAssetController::class, 'store'])->name('fa.store');
        Route::post('fixed-assets/run-depreciation', [FixedAssetController::class, 'runDepreciation'])->name('fa.depr');
        Route::post('fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])->name('fa.dispose');

        // VAT / Mushak (always-on module). Statutory rules are data — configured,
        // approved (= practitioner-verified) before they can classify.
        Route::get('vat/rates', [VatController::class, 'rates'])->name('vat.rates');
        Route::get('vat/transaction-types', [VatController::class, 'types'])->name('vat.types');
        Route::get('vat/tax-treatments', [VatController::class, 'treatments'])->name('vat.treatments');
        Route::get('vat/notes', [VatController::class, 'notes'])->name('vat.notes.list');
        Route::get('vat/mappings', [VatController::class, 'mappings'])->name('vat.mappings.list');
        Route::post('vat/rates', [VatController::class, 'storeRate'])->name('vat.rate');
        Route::post('vat/transaction-types', [VatController::class, 'storeType'])->name('vat.type');
        Route::post('vat/tax-treatments', [VatController::class, 'storeTreatment'])->name('vat.treatment');
        Route::post('vat/notes', [VatController::class, 'storeNote'])->name('vat.note');
        Route::post('vat/mappings', [VatController::class, 'storeMapping'])->name('vat.mapping');
        Route::patch('vat/mappings/{mapping}/approve', [VatController::class, 'approveMapping'])->name('vat.mapping.approve');
        Route::post('vat/classify', [VatController::class, 'classify'])->name('vat.classify');
        Route::get('vat/note-summary', [VatController::class, 'noteSummary'])->name('vat.notesummary');
        Route::get('vat/unmapped', [VatController::class, 'unmapped'])->name('vat.unmapped');
        Route::get('vat/register', [VatController::class, 'register'])->name('vat.register');

        // VDS — configurable, verified rules; deduction / certificate / treasury deposit.
        Route::get('vds/rules', [VdsController::class, 'rules'])->name('vds.rules');
        Route::get('vds/deductions', [VdsController::class, 'deductions'])->name('vds.deductions');
        Route::post('vds/rules', [VdsController::class, 'storeRule'])->name('vds.rule');
        Route::patch('vds/rules/{rule}/approve', [VdsController::class, 'approveRule'])->name('vds.rule.approve');
        Route::post('vds/deductions', [VdsController::class, 'deduct'])->name('vds.deduct');
        Route::post('vds/deductions/{deduction}/certificate', [VdsController::class, 'issueCertificate'])->name('vds.cert');
        Route::post('vds/deposits', [VdsController::class, 'deposit'])->name('vds.deposit');
        Route::get('vds/reconciliation', [VdsController::class, 'reconciliation'])->name('vds.recon');

        // Contract manufacturing / job work (customer-owned material custody).
        Route::get('job-work/contracts', [JobWorkController::class, 'contracts'])->name('jw.contracts');
        Route::get('job-work/orders', [JobWorkController::class, 'orders'])->name('jw.orders');
        Route::post('job-work/contracts', [JobWorkController::class, 'storeContract'])->name('jw.contract');
        Route::post('job-work/orders', [JobWorkController::class, 'storeOrder'])->name('jw.order');
        Route::post('job-work/orders/{jobWorkOrder}/receive-material', [JobWorkController::class, 'receiveMaterial'])->name('jw.recv');
        Route::post('job-work/orders/{jobWorkOrder}/consume-material', [JobWorkController::class, 'consumeMaterial'])->name('jw.consume');
        Route::post('job-work/orders/{jobWorkOrder}/deliver', [JobWorkController::class, 'deliver'])->name('jw.deliver');
        Route::post('job-work/processing-invoices', [JobWorkController::class, 'processingInvoice'])->name('jw.pinv');
        Route::get('job-work/material-reconciliation', [JobWorkController::class, 'reconciliation'])->name('jw.recon');

        // Reporting (secured; PDF/XLSX/HTML/JSON via one engine).
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{key}', [ReportController::class, 'show'])->name('reports.show');

        // MIS dashboards + Approval Center.
        Route::get('mis/executive', [MisController::class, 'executive'])->name('mis.exec');
        Route::get('mis/dashboard', [MisController::class, 'dashboard'])->name('mis.dashboard');
        Route::get('mis/approvals', [MisController::class, 'approvals'])->name('mis.approvals');

        // Per-company settings store (general, numbering, email, master lists, …).
        Route::get('settings/{group}', [SettingController::class, 'show'])->name('settings.show');
        Route::put('settings/{group}', [SettingController::class, 'update'])->name('settings.update');

        // Document Center (private, tenant-scoped file repository).
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        // Generic approval workflow (all document types).
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

        // Backup & System Health.
        Route::get('backup/jobs', [BackupController::class, 'jobs'])->name('backup.jobs');
        Route::post('backup/run', [BackupController::class, 'run'])->name('backup.run');
        Route::get('backup/health', [BackupController::class, 'health'])->name('backup.health');
    });
});
