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
}
