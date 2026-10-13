<?php
namespace App\Modules\Backup\Dumpers;
use App\Modules\Backup\Contracts\DatabaseDumper;
use RuntimeException;

class SqliteDumper implements DatabaseDumper {
    public function __construct(private string $databasePath) {}
    public function dump(): string {
        if (! is_file($this->databasePath)) {
            throw new RuntimeException("SQLite database file not found: {$this->databasePath}");
        }
        $tmp = tempnam(sys_get_temp_dir(), 'dbdump');
        copy($this->databasePath, $tmp);
        return $tmp;
    }
}
