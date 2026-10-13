<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ImportShipmentLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'import_shipment_id', 'product_id', 'batch_id', 'qty',
        'fc_unit_price', 'weight', 'volume', 'landed_unit_cost',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'fc_unit_price' => 'decimal:4',
        'weight' => 'decimal:4',
        'volume' => 'decimal:4',
        'landed_unit_cost' => 'decimal:4',
    ];
}
