<?php

namespace App\Modules\FixedAssets\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AssetMaintenance extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'fixed_asset_id', 'maint_no', 'date', 'amount', 'description', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:4'];
}
