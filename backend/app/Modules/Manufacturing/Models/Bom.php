<?php

namespace App\Modules\Manufacturing\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bom extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'bom_no', 'product_id', 'version', 'output_qty',
        'effective_from', 'effective_to', 'status',
    ];

    protected $casts = ['output_qty' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class);
    }
}
