<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'purchase_invoice_id', 'product_id', 'account_id',
        'description', 'qty', 'unit_price', 'line_total',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];
}
