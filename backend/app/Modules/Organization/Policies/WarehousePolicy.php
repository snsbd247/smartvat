<?php

namespace App\Modules\Organization\Policies;

use App\Models\User;
use App\Modules\Organization\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('warehouse.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.view');
    }

    public function create(User $user): bool
    {
        return $user->can('warehouse.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.update');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.delete');
    }
}
