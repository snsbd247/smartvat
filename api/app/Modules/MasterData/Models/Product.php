<?php

namespace App\Modules\MasterData\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'type', 'base_uom',
        'costing_method', 'track_batches', 'status',
    ];

    protected $casts = ['track_batches' => 'boolean'];

    public const COSTING_WEIGHTED_AVERAGE = 'weighted_average';

    public const COSTING_FIFO = 'fifo';
}
