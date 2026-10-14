<?php

namespace App\Modules\FixedAssets\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DepreciationEntry extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'fixed_asset_id', 'period', 'amount', 'date'];

    protected $casts = ['amount' => 'decimal:4', 'date' => 'date'];
}
