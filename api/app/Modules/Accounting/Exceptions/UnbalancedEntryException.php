<?php

namespace App\Modules\Accounting\Exceptions;

use RuntimeException;

class UnbalancedEntryException extends RuntimeException
{
    public function __construct(string $debit, string $credit)
    {
        parent::__construct("Journal entry is not balanced: debit {$debit} != credit {$credit}.");
    }
}
