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
use App\Modules\Organization\Http\Controllers\CompanyConfigController;
use App\Modules\Organization\Http\Controllers\CompanyController;
use App\Modules\Organization\Http\Controllers\FactoryController;
use App\Modules\Organization\Http\Controllers\WarehouseController;
use App\Modules\Security\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (prefix: /api)
|--------------------------------------------------------------------------
*/

// Public.
Route::get('/health', HealthController::class)->name('health');
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

// Authenticated (any company the user belongs to).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
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

        // Master data (always-on module).
        Route::apiResource('products', ProductController::class);

        // Purchase (always-on module): suppliers + procure-to-pay.
        Route::apiResource('suppliers', SupplierController::class);
        Route::post('purchase-orders', [PurchaseController::class, 'storeOrder'])->name('po.store');
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseController::class, 'approveOrder'])->name('po.approve');
        Route::post('goods-receipts', [PurchaseController::class, 'receiveGoods'])->name('grn.store');
        Route::post('purchase-invoices', [PurchaseController::class, 'postInvoice'])->name('pi.store');
        Route::post('supplier-payments', [PurchaseController::class, 'paySupplier'])->name('supplier.pay');
        Route::get('suppliers/{supplier}/ledger', [PurchaseController::class, 'supplierLedger'])->name('supplier.ledger');

        // Import purchase + landed cost.
        Route::post('import-shipments', [ImportController::class, 'store'])->name('import.store');
        Route::get('import-shipments/{importShipment}/landed-cost', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('import-shipments/{importShipment}/receive', [ImportController::class, 'receive'])->name('import.receive');

        // Sales (always-on module): customers + local/export order-to-cash.
        Route::apiResource('customers', CustomerController::class);
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

        // Manufacturing — gated: only companies with the production module enabled.
        Route::middleware('module:production')->group(function () {
            Route::post('boms', [ProductionController::class, 'storeBom'])->name('bom.store');
            Route::post('production-orders', [ProductionController::class, 'storeOrder'])->name('prod.store');
            Route::post('production-orders/{productionOrder}/issue', [ProductionController::class, 'issue'])->name('prod.issue');
            Route::post('production-orders/{productionOrder}/receive', [ProductionController::class, 'receive'])->name('prod.receive');
        });
    });
});
