<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class StockCountLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'stock_count_id', 'product_id', 'system_qty', 'counted_qty', 'variance'];
    protected $casts = ['system_qty' => 'decimal:4', 'counted_qty' => 'decimal:4', 'variance' => 'decimal:4'];
}
