<?php

use App\Http\Controllers\System\HealthController;
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

        // Inventory module — gated: only companies with the inventory module enabled.
        Route::middleware('module:inventory')->group(function () {
            Route::apiResource('warehouses', WarehouseController::class)
                ->only(['index', 'store', 'show', 'destroy']);
        });
    });
});
