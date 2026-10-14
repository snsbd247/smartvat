<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportShipment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'shipment_no', 'supplier_id', 'warehouse_id', 'currency',
        'exchange_rate', 'incoterm', 'bl_no', 'date', 'reference', 'status',
        'goods_value_bc', 'landed_value_bc', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'exchange_rate' => 'decimal:8',
        'goods_value_bc' => 'decimal:4',
        'landed_value_bc' => 'decimal:4',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(ImportShipmentLine::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ImportCharge::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
