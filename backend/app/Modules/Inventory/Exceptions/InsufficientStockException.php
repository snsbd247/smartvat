<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(int $productId, int $warehouseId, string $available, string $requested)
    {
        parent::__construct(
            "Insufficient stock for product {$productId} in warehouse {$warehouseId}: ".
            "available {$available}, requested {$requested}."
        );
    }
}
