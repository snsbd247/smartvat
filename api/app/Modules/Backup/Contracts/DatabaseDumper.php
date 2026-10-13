<?php
namespace App\Modules\Backup\Contracts;
interface DatabaseDumper {
    public function dump(): string; // path to a raw dump file
}
