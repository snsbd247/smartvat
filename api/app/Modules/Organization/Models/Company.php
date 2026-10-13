<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'code', 'name', 'legal_name', 'logo_path', 'address', 'phone', 'email',
        'country', 'base_currency', 'bin', 'vat_reg_no', 'status',
    ];

    public function factories(): HasMany
    {
        return $this->hasMany(Factory::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_company_access')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function businessTypes(): HasMany
    {
        return $this->hasMany(CompanyBusinessType::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CompanyModule::class);
    }

    /** Whether a given module is enabled for this company (module gate source). */
    public function hasModule(string $key): bool
    {
        return $this->modules()->where('module_key', $key)->where('enabled', true)->exists();
    }

    /** Enabled module keys (for the frontend sidebar manifest). */
    public function enabledModuleKeys(): array
    {
        return $this->modules()->where('enabled', true)->pluck('module_key')->all();
    }

    public function primaryBusinessType(): ?string
    {
        return $this->businessTypes()->where('is_primary', true)->value('type');
    }
}
