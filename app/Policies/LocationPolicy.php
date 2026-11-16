<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('warehouses.manage');
    }

    public function view(User $user, Location $location): bool
    {
        return $user->can('warehouses.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('warehouses.manage');
    }

    public function update(User $user, Location $location): bool
    {
        return $user->can('warehouses.manage');
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->can('warehouses.manage');
    }
}
