<?php

namespace App\Modules\Organization\Models;

use App\Shared\Audit\Auditable;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'factory_id', 'code', 'name', 'type', 'status'];

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function storageLocations(): HasMany
    {
        return $this->hasMany(StorageLocation::class);
    }
}
