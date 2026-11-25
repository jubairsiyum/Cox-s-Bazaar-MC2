<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Location;
use App\Models\User;
use App\Services\BranchScope;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.manage');
    }

    public function view(User $user, Location $location): bool
    {
        return $user->can('branches.manage')
            && BranchScope::canAccess($user, $location->id);
    }

    public function create(User $user): bool
    {
        return $user->can('branches.manage')
            && BranchScope::isUnrestricted($user);
    }

    public function update(User $user, Location $location): bool
    {
        return $user->can('branches.manage')
            && BranchScope::canAccess($user, $location->id);
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->can('branches.manage')
            && BranchScope::canAccess($user, $location->id);
    }
}
