<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'payment_no', 'supplier_id', 'date', 'amount',
        'paid_from_account_id', 'reference', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:4'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
