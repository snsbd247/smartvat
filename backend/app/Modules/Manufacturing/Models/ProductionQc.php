<?php

namespace App\Modules\Manufacturing\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionQc extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'production_order_id', 'qc_no', 'date', 'inspected_qty',
        'passed_qty', 'rejected_qty', 'inspector', 'result', 'notes', 'created_by',
    ];

    protected $casts = [
        'date' => 'date', 'inspected_qty' => 'decimal:4',
        'passed_qty' => 'decimal:4', 'rejected_qty' => 'decimal:4',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
