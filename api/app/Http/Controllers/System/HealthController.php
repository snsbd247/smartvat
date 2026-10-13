<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Liveness/readiness probe.
     *
     * Reports application status and database connectivity. Used by the deploy
     * health check and by uptime monitoring. Never leaks sensitive config.
     */
    public function __invoke(): JsonResponse
    {
        $database = 'ok';
        $httpStatus = 200;

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (\Throwable $e) {
            $database = 'unavailable';
            $httpStatus = 503;
        }

        return response()->json([
            'status' => $httpStatus === 200 ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'version' => config('app.version', '0.1.0-dev'),
            'environment' => config('app.env'),
            'time' => now()->toIso8601String(),
            'checks' => [
                'database' => $database,
            ],
        ], $httpStatus);
    }
}
