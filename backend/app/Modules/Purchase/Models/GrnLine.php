<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class GrnLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'goods_receipt_note_id', 'product_id', 'batch_id',
        'qty', 'unit_cost', 'line_value',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'line_value' => 'decimal:4',
    ];
}
