<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('units.view') || $user->can('units.manage');
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->can('units.view') || $user->can('units.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('units.manage');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->can('units.manage');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->can('units.manage');
    }

    public function import(User $user): bool
    {
        return $user->can('units.manage');
    }
}
