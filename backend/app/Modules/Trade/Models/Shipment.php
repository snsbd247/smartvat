<?php

namespace App\Modules\Trade\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'shipment_no', 'direction', 'transport_ref', 'carrier', 'origin', 'destination', 'etd', 'eta', 'status', 'note', 'created_by'];
    protected $casts = ['etd' => 'date', 'eta' => 'date'];
}
