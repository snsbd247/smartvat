<?php
namespace App\Modules\Backup\Storage;
use App\Modules\Backup\Contracts\BackupStorage;
use RuntimeException;

/**
 * Google Drive driver (least-privilege service account, dedicated folder).
 * Implemented as a guarded stub: it requires GOOGLE_DRIVE_CREDENTIALS + folder id and
 * the google/apiclient package. Until configured it fails loudly rather than pretending
 * a backup was uploaded. Wire the Drive API calls here for production.
 */
class GoogleDriveStorage implements BackupStorage {
    public function __construct(private ?string $folderId, private ?string $credentialsJson) {}
    private function guard(): void {
        if (empty($this->folderId) || empty($this->credentialsJson)) {
            throw new RuntimeException('Google Drive backup is not configured (folder id + service-account credentials required).');
        }
        if (! class_exists(\Google\Client::class)) {
            throw new RuntimeException('google/apiclient is not installed. Run: composer require google/apiclient');
        }
    }
    public function put(string $localPath, string $name): string { $this->guard(); throw new RuntimeException('GoogleDriveStorage::put not yet implemented — see class docblock.'); }
    public function get(string $ref): string { $this->guard(); throw new RuntimeException('GoogleDriveStorage::get not yet implemented.'); }
    public function delete(string $ref): void { $this->guard(); }
}
