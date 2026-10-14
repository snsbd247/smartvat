<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'purchase_order_id', 'product_id', 'description',
        'is_service', 'qty', 'unit_price', 'line_total',
    ];

    protected $casts = [
        'is_service' => 'boolean',
        'qty' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];
}
