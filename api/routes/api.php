<?php

use App\Http\Controllers\System\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes here are prefixed with /api. Versioned module routes will be
| registered per-module from Phase 2 onward.
*/

// Public health / readiness probe (no auth) — used by deploy + monitoring.
Route::get('/health', HealthController::class)->name('health');

// Authenticated current-user endpoint (Sanctum).
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum')->name('user.me');
