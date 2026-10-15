<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'count_no', 'warehouse_id', 'date', 'status', 'note', 'created_by'];
    protected $casts = ['date' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }
}
