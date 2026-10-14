<?php

namespace App\Modules\Backup\Services;

use App\Modules\Backup\Contracts\BackupStorage;
use App\Modules\Backup\Contracts\DatabaseDumper;
use App\Modules\Backup\Models\BackupJob;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Database backup pipeline: dump → gzip → AES-256 encrypt → checksum → upload → verify.
 * A backup counts as successful ONLY after the remote upload is checksum-verified.
 * Retention never deletes the last good backup.
 */
class BackupService
{
    public function __construct(
        private BackupStorage $storage,
        private DatabaseDumper $dumper,
        private string $encryptionKey,
        private int $retentionDays,
    ) {}

    public function run(): BackupJob
    {
        $job = BackupJob::create([
            'filename' => 'backup_'.now()->format('Ymd_His').'.enc',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $rawPath = $this->dumper->dump();
            $raw = file_get_contents($rawPath);
            @unlink($rawPath);

            $artifact = $this->encrypt(gzencode($raw, 9));
            $checksum = hash('sha256', $artifact);

            $tmp = tempnam(sys_get_temp_dir(), 'bkp');
            file_put_contents($tmp, $artifact);
            $ref = $this->storage->put($tmp, $job->filename);
            @unlink($tmp);

            // Verify the uploaded artifact by reading it back.
            $readback = $this->storage->get($ref);
            if (hash('sha256', $readback) !== $checksum) {
                throw new RuntimeException('Upload verification failed: checksum mismatch.');
            }

            $job->update([
                'remote_ref' => $ref,
                'checksum' => $checksum,
                'size' => strlen($artifact),
                'uploaded' => true,
                'verified' => true,
                'status' => 'success',
                'finished_at' => now(),
            ]);

            $this->cleanup();
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
            Log::error('Backup failed', ['job' => $job->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        return $job->fresh();
    }

    /** Restore a verified backup's raw dump to a target path (round-trip verified). */
    public function restore(BackupJob $job, string $targetPath): void
    {
        $bytes = $this->storage->get($job->remote_ref);
        if ($job->checksum && hash('sha256', $bytes) !== $job->checksum) {
            throw new RuntimeException('Restore aborted: artifact checksum does not match the recorded backup.');
        }
        $raw = gzdecode($this->decrypt($bytes));
        file_put_contents($targetPath, $raw);
    }

    /**
     * Delete backups older than the retention window — but never the most recent good
     * one, and never when it is the only verified backup.
     */
    public function cleanup(): int
    {
        $successes = BackupJob::where('status', 'success')->orderByDesc('finished_at')->get();
        if ($successes->count() <= 1) {
            return 0; // keep the only good backup no matter how old
        }

        $cutoff = now()->subDays($this->retentionDays);
        $deleted = 0;
        foreach ($successes->slice(1) as $job) { // always keep the most recent (index 0)
            if ($job->finished_at !== null && $job->finished_at->lt($cutoff)) {
                if ($job->remote_ref) {
                    $this->storage->delete($job->remote_ref);
                }
                $job->delete();
                $deleted++;
            }
        }

        return $deleted;
    }

    private function encrypt(string $data): string
    {
        $key = hash('sha256', $this->encryptionKey, true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return $iv.$cipher; // iv is prepended
    }

    private function decrypt(string $data): string
    {
        $key = hash('sha256', $this->encryptionKey, true);
        $iv = substr($data, 0, 16);
        $cipher = substr($data, 16);

        return openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    }
}
