<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'inv_no', 'customer_id', 'sales_order_id', 'kind', 'currency',
        'exchange_rate', 'date', 'subtotal_fc', 'subtotal', 'total', 'narration',
        'status', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'exchange_rate' => 'decimal:8',
        'subtotal_fc' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class);
    }
}
