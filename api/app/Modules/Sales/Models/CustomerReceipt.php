<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CustomerReceipt extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'receipt_no', 'customer_id', 'date', 'currency', 'fc_amount',
        'realization_rate', 'invoice_rate', 'amount', 'received_in_account_id', 'reference', 'created_by',
    ];

    protected $casts = [
        'date' => 'date', 'fc_amount' => 'decimal:4', 'realization_rate' => 'decimal:8',
        'invoice_rate' => 'decimal:8', 'amount' => 'decimal:4',
    ];
}
