<?php

namespace App\Modules\Purchase\Policies;

use App\Models\User;
use App\Modules\Purchase\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.delete');
    }
}
