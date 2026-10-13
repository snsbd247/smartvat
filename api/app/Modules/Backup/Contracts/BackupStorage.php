<?php
namespace App\Modules\Backup\Contracts;
interface BackupStorage {
    public function put(string $localPath, string $name): string; // returns remote ref
    public function get(string $ref): string;                     // returns bytes
    public function delete(string $ref): void;
}
