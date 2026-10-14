<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class LandedCostAllocation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'import_shipment_id', 'import_charge_id',
        'import_shipment_line_id', 'amount',
    ];

    protected $casts = ['amount' => 'decimal:4'];
}
