<?php

namespace App\Modules\Organization\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageLocation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'warehouse_id', 'code', 'name', 'status'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
