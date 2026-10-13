<?php

namespace App\Modules\Accounting\Exceptions;

use RuntimeException;

class PeriodClosedException extends RuntimeException
{
    public function __construct(string $date)
    {
        parent::__construct("The accounting period for {$date} is closed or locked; posting is not allowed.");
    }
}
