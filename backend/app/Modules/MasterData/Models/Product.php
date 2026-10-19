<?php

namespace App\Modules\MasterData\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'type', 'category_id', 'base_uom',
        'costing_method', 'track_batches', 'reorder_level', 'status',
    ];

    protected $casts = [
        'track_batches' => 'boolean',
        'reorder_level' => 'decimal:4',
    ];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public const COSTING_WEIGHTED_AVERAGE = 'weighted_average';

    public const COSTING_FIFO = 'fifo';

    public const COSTING_LIFO = 'lifo';
}
