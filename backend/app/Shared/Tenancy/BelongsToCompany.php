<?php

namespace App\Shared\Tenancy;

use App\Modules\Organization\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-scoped model. Adds the CompanyScope global scope
 * (reads are confined to the current company) and auto-stamps company_id on
 * create from the active tenant when not explicitly provided.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $current = app(CurrentCompany::class);
                if ($current->isset()) {
                    $model->company_id = $current->id();
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
