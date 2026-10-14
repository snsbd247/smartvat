<?php

namespace App\Shared\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains every query on a tenant model to the current
 * company. This is the backbone of tenant isolation: without an active company
 * the scope matches nothing, so data never leaks across tenants by accident.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $current = app(CurrentCompany::class);

        if ($current->isset()) {
            $builder->where($model->getTable().'.company_id', $current->id());
        } else {
            // Fail closed: no tenant context => no tenant rows.
            $builder->whereRaw('1 = 0');
        }
    }
}
