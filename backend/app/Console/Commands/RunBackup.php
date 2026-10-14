<?php

namespace App\Console\Commands;

use App\Modules\Backup\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Create, encrypt, upload and verify a database backup (scheduled every 20 min).';

    public function handle(BackupService $backup): int
    {
        try {
            $job = $backup->run();
            $this->info("Backup {$job->filename} succeeded (verified, {$job->size} bytes).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
