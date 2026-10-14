<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class StockBalance extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'product_id', 'warehouse_id', 'batch_id',
        'owner_type', 'owner_id', 'qty', 'value',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'value' => 'decimal:4',
    ];
}
