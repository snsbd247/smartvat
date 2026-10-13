<?php

namespace App\Modules\Organization\Models;

use App\Shared\Audit\Auditable;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factory extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'address', 'status'];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
