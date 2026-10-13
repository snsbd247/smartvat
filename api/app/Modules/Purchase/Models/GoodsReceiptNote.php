<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptNote extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'grn_no', 'supplier_id', 'purchase_order_id', 'warehouse_id',
        'date', 'reference', 'status', 'total_value', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'total_value' => 'decimal:4'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrnLine::class);
    }
}
