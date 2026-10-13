<?php
namespace App\Modules\Backup\Storage;
use App\Modules\Backup\Contracts\BackupStorage;
use RuntimeException;

/** Default driver — writes encrypted artifacts to a local directory. */
class LocalBackupStorage implements BackupStorage {
    public function __construct(private string $dir) {
        if (! is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }
    public function put(string $localPath, string $name): string {
        $dest = rtrim($this->dir, '/\').DIRECTORY_SEPARATOR.$name;
        if (! @copy($localPath, $dest)) {
            throw new RuntimeException("Failed to store backup at {$dest}");
        }
        return $name;
    }
    public function get(string $ref): string {
        $path = rtrim($this->dir, '/\').DIRECTORY_SEPARATOR.$ref;
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException("Backup artifact not found: {$ref}");
        }
        return $bytes;
    }
    public function delete(string $ref): void {
        @unlink(rtrim($this->dir, '/\').DIRECTORY_SEPARATOR.$ref);
    }
}
