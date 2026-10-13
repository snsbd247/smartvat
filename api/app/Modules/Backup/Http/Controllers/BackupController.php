<?php

namespace App\Modules\Backup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Models\BackupJob;
use App\Modules\Backup\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BackupController extends Controller
{
    public function jobs(): JsonResponse
    {
        $this->authorize('backup-view');

        return response()->json(
            BackupJob::orderByDesc('id')->limit(50)->get()
        );
    }

    public function run(BackupService $backup): JsonResponse
    {
        $this->authorize('backup-manage');

        try {
            $job = $backup->run();

            return response()->json($job, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Backup failed: '.$e->getMessage()], 500);
        }
    }

    public function health(): JsonResponse
    {
        $this->authorize('backup-view');

        $db = 'ok';
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $db = 'unavailable';
        }

        $last = BackupJob::where('status', 'success')->orderByDesc('finished_at')->first();

        return response()->json([
            'api' => 'ok',
            'database' => $db,
            'last_successful_backup' => $last?->finished_at,
            'last_backup_verified' => (bool) ($last?->verified),
            'failed_last_24h' => BackupJob::where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }
}
