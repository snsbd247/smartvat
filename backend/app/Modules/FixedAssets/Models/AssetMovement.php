<?php

namespace App\Modules\FixedAssets\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AssetMovement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'fixed_asset_id', 'date', 'from_location', 'to_location',
        'from_custodian', 'to_custodian', 'note', 'created_by',
    ];

    protected $casts = ['date' => 'date'];
}
