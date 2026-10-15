<?php

namespace App\Modules\Backup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Models\BackupJob;
use App\Modules\Backup\Services\BackupService;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /** Current Google Drive backup configuration (never returns the raw credentials). */
    public function driveConfig(): JsonResponse
    {
        $this->authorize('backup-manage');
        $drive = SystemSetting::get('backup.drive');

        return response()->json([
            'enabled' => (bool) ($drive['enabled'] ?? false),
            'folder_id' => $drive['folder_id'] ?? null,
            'credentials_set' => ! empty($drive['credentials_json']),
        ]);
    }

    /** Save Google Drive backup configuration from the admin panel. */
    public function saveDriveConfig(Request $request): JsonResponse
    {
        $this->authorize('backup-manage');

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'folder_id' => ['nullable', 'string', 'max:255'],
            'credentials_json' => ['nullable', 'string'], // paste the service-account JSON; omit to keep existing
        ]);

        $current = SystemSetting::get('backup.drive');
        $credentials = $current['credentials_json'] ?? null;

        if (! empty($data['credentials_json'])) {
            $decoded = json_decode($data['credentials_json'], true);
            if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
                return response()->json(['message' => 'Invalid service-account JSON (needs client_email and private_key).'], 422);
            }
            $credentials = $data['credentials_json'];
        }

        if ($data['enabled'] && (empty($data['folder_id']) || empty($credentials))) {
            return response()->json(['message' => 'A folder id and service-account JSON are required to enable Google Drive backup.'], 422);
        }

        SystemSetting::put('backup.drive', [
            'enabled' => $data['enabled'],
            'folder_id' => $data['folder_id'] ?? null,
            'credentials_json' => $credentials,
        ]);

        return response()->json(['message' => 'Google Drive backup settings saved.']);
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
