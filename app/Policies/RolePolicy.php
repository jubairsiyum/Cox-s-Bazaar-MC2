<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view') || $user->can('roles.manage');
    }

    /**
     * Determine whether the user can view the given role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view') || $user->can('roles.manage');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    /**
     * Determine whether the user can update the given role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }

    /**
     * Determine whether the user can delete the given role.
     */
    public function delete(User $user, Role $role): bool
    {
        // The Super Admin role must never be removed.
        if ($role->name === 'Super Admin') {
            return false;
        }

        return $user->can('roles.manage');
    }
}
