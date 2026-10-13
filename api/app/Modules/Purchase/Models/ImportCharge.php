<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ImportCharge extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'import_shipment_id', 'type', 'currency', 'fc_amount',
        'exchange_rate', 'bc_amount', 'recoverable', 'allocation_basis',
    ];

    protected $casts = [
        'fc_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:8',
        'bc_amount' => 'decimal:4',
        'recoverable' => 'boolean',
    ];
}
