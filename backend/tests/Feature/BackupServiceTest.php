<?php

use App\Modules\Backup\Contracts\DatabaseDumper;
use App\Modules\Backup\Models\BackupJob;
use App\Modules\Backup\Services\BackupService;
use App\Modules\Backup\Storage\LocalBackupStorage;

function fakeDumper(string $content): DatabaseDumper
{
    return new class($content) implements DatabaseDumper
    {
        public function __construct(private string $content) {}

        public function dump(): string
        {
            $p = tempnam(sys_get_temp_dir(), 'rawdump');
            file_put_contents($p, $this->content);

            return $p;
        }
    };
}

function makeBackupService(string $dir, string $content = 'DATABASE-CONTENT-123', int $retention = 30): BackupService
{
    return new BackupService(new LocalBackupStorage($dir), fakeDumper($content), 'test-key', $retention);
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/bkp_test_'.uniqid();
    @mkdir($this->dir, 0775, true);
});

it('runs a verified, encrypted backup', function () {
    $job = makeBackupService($this->dir)->run();

    expect($job->status)->toBe('success');
    expect($job->verified)->toBeTrue();
    expect($job->uploaded)->toBeTrue();
    expect($job->checksum)->not->toBeNull();

    // The stored artifact exists and is NOT the plaintext (it is encrypted).
    $artifact = file_get_contents($this->dir.'/'.$job->remote_ref);
    expect($artifact)->not->toContain('DATABASE-CONTENT-123');
    expect(hash('sha256', $artifact))->toBe($job->checksum);
});

it('restores a backup to the original content (round-trip)', function () {
    $svc = makeBackupService($this->dir, 'HELLO-RESTORE-ME');
    $job = $svc->run();

    $target = $this->dir.'/restored.sql';
    $svc->restore($job, $target);

    expect(file_get_contents($target))->toBe('HELLO-RESTORE-ME');
});

it('never deletes the only good backup, however old', function () {
    $svc = makeBackupService($this->dir);
    $job = $svc->run();
    $job->update(['finished_at' => now()->subDays(90)]);

    $deleted = $svc->cleanup();

    expect($deleted)->toBe(0);
    expect(BackupJob::where('status', 'success')->count())->toBe(1);
});

it('deletes backups older than retention once a newer verified backup exists', function () {
    $svc = makeBackupService($this->dir, 'X', retention: 30);

    // An old verified backup (push its date back before the next run).
    $old = $svc->run();
    $old->update(['finished_at' => now()->subDays(40)]);

    // A fresh verified backup — its run() triggers retention cleanup internally.
    $svc->run();

    // The old backup (older than retention, with a newer verified backup present) is gone.
    expect(BackupJob::whereKey($old->id)->exists())->toBeFalse();
    expect(BackupJob::where('status', 'success')->count())->toBe(1);
});
