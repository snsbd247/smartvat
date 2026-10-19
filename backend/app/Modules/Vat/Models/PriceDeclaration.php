<?php

namespace App\Modules\Vat\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceDeclaration extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'decl_no', 'product_id', 'declared_price', 'purchase_value',
        'effective_from', 'status', 'note', 'created_by',
    ];

    protected $casts = [
        'declared_price' => 'decimal:4', 'purchase_value' => 'decimal:4', 'effective_from' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MasterData\Models\Product::class, 'product_id');
    }
}
