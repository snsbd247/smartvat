<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class StockLayer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'product_id', 'warehouse_id', 'batch_id', 'owner_type', 'owner_id',
        'unit_cost', 'qty_received', 'qty_remaining', 'received_at',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:6', 'qty_received' => 'decimal:4', 'qty_remaining' => 'decimal:4',
        'received_at' => 'datetime',
    ];
}
