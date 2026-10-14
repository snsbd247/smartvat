<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SalesReturnLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'sales_return_id', 'product_id', 'batch_id', 'qty', 'unit_price', 'line_total'];

    protected $casts = ['qty' => 'decimal:4', 'unit_price' => 'decimal:4', 'line_total' => 'decimal:4'];
}
