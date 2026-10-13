<?php

namespace App\Modules\FixedAssets\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'method', 'useful_life_months',
        'asset_account_id', 'depreciation_expense_account_id', 'accumulated_account_id',
    ];
}
