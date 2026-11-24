<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DayClosing;
use App\Models\User;
use App\Services\BranchScope;

class DayClosingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['finance.view', 'finance.manage', 'dashboard.view']);
    }

    public function view(User $user, DayClosing $dayClosing): bool
    {
        return $this->viewAny($user)
            && BranchScope::canAccess($user, $dayClosing->location_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['finance.manage', 'dashboard.view'])
            && (BranchScope::locationId($user) !== null || BranchScope::isUnrestricted($user));
    }

    public function reopen(User $user, DayClosing $dayClosing): bool
    {
        // Only Super Admin can reopen a closed day (per Ecosystem Plan rule)
        return $user->isSuperAdmin();
    }
}
