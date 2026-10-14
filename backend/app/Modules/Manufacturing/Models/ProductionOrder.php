<?php

namespace App\Modules\Manufacturing\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'order_no', 'product_id', 'bom_id', 'warehouse_id', 'planned_qty',
        'produced_qty', 'scrap_qty', 'wip_value', 'produced_cost', 'date', 'status', 'created_by',
    ];

    protected $casts = [
        'planned_qty' => 'decimal:4', 'produced_qty' => 'decimal:4', 'scrap_qty' => 'decimal:4',
        'wip_value' => 'decimal:4', 'produced_cost' => 'decimal:4', 'date' => 'date',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }
}
