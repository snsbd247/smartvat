<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DeliveryChallanLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'delivery_challan_id', 'product_id', 'batch_id', 'qty'];

    protected $casts = ['qty' => 'decimal:4'];
}
