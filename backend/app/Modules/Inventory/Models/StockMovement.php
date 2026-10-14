<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'product_id', 'warehouse_id', 'batch_id', 'owner_type', 'owner_id',
        'qty', 'unit_cost', 'value', 'balance_qty', 'balance_value',
        'source_type', 'source_id', 'reference', 'moved_at', 'created_by', 'created_at',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'value' => 'decimal:4',
        'balance_qty' => 'decimal:4',
        'balance_value' => 'decimal:4',
        'moved_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
