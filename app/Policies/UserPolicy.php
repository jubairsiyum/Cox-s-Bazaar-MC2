<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the given user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the given user.
     */
    public function update(User $user, User $model): bool
    {
        // A Super Admin may be edited only by another Super Admin.
        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->can('users.update');
    }

    /**
     * Determine whether the user can deactivate or reactivate the given user.
     */
    public function toggleStatus(User $user, User $model): bool
    {
        // Users may not deactivate themselves, which would lock them out.
        if ($user->is($model)) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->can('users.deactivate');
    }

    /**
     * Determine whether the user can delete the given user.
     */
    public function delete(User $user, User $model): bool
    {
        // Users may not delete their own account.
        if ($user->is($model)) {
            return false;
        }

        if ($model->isSuperAdmin()) {
            return false;
        }

        return $user->can('users.deactivate');
    }
}
