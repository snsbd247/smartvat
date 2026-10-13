<?php
namespace App\Modules\Backup\Dumpers;
use App\Modules\Backup\Contracts\DatabaseDumper;
use RuntimeException;

class MysqlDumper implements DatabaseDumper {
    public function __construct(
        private string $host, private int $port, private string $database,
        private string $username, private string $password,
    ) {}
    public function dump(): string {
        $tmp = tempnam(sys_get_temp_dir(), 'dbdump').'.sql';
        $cmd = sprintf(
            'mysqldump --single-transaction --quick --host=%s --port=%d --user=%s %s %s > %s',
            escapeshellarg($this->host), $this->port, escapeshellarg($this->username),
            $this->password !== '' ? '--password='.escapeshellarg($this->password) : '',
            escapeshellarg($this->database), escapeshellarg($tmp),
        );
        exec($cmd, $out, $code);
        if ($code !== 0) {
            throw new RuntimeException('mysqldump failed with exit code '.$code);
        }
        return $tmp;
    }
}
