<?php

namespace App\Modules\Sales\Models;

use App\Modules\MasterData\Models\Product;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'sales_invoice_id', 'product_id', 'description',
        'qty', 'unit_price', 'line_total',
    ];

    protected $casts = ['qty' => 'decimal:4', 'unit_price' => 'decimal:4', 'line_total' => 'decimal:4'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
