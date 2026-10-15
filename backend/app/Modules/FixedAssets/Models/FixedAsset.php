<?php

namespace App\Modules\FixedAssets\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAsset extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'asset_no', 'asset_category_id', 'name', 'acquisition_date',
        'cost', 'salvage_value', 'useful_life_months', 'method',
        'accumulated_depreciation', 'book_value', 'status', 'location', 'custodian', 'created_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'cost' => 'decimal:4', 'salvage_value' => 'decimal:4',
        'accumulated_depreciation' => 'decimal:4', 'book_value' => 'decimal:4',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }
}
