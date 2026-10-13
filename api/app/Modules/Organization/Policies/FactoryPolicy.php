<?php

namespace App\Modules\Organization\Policies;

use App\Models\User;
use App\Modules\Organization\Models\Factory;

class FactoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('factory.view');
    }

    public function view(User $user, Factory $factory): bool
    {
        return $user->can('factory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('factory.create');
    }

    public function update(User $user, Factory $factory): bool
    {
        return $user->can('factory.update');
    }

    public function delete(User $user, Factory $factory): bool
    {
        return $user->can('factory.delete');
    }
}
