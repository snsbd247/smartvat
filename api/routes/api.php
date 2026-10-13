<?php

use App\Http\Controllers\System\HealthController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\MasterData\Http\Controllers\ProductController;
use App\Modules\Purchase\Http\Controllers\ImportController;
use App\Modules\Purchase\Http\Controllers\PurchaseController;
use App\Modules\Purchase\Http\Controllers\SupplierController;
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
    });
});
