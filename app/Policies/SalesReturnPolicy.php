<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesReturn;
use App\Models\User;
use App\Services\SalesScope;

class SalesReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales-returns.manage')
            || $user->hasAnyRole(['Manager', 'Outlet Manager']);
    }

    /**
     * Managing users and the responsible branch managers (who must be able to
     * open a return to accept or reject it) may view it.
     */
    public function view(User $user, SalesReturn $salesReturn): bool
    {
        return ($user->can('sales-returns.manage') && SalesScope::canAccessRecord($user, $salesReturn))
            || SalesScope::canApprove($user, $salesReturn);
    }

    public function create(User $user): bool
    {
        return $user->can('sales-returns.manage');
    }

    public function update(User $user, SalesReturn $salesReturn): bool
    {
        return $user->can('sales-returns.manage')
            && SalesScope::canAccessRecord($user, $salesReturn);
    }

    public function delete(User $user, SalesReturn $salesReturn): bool
    {
        return $this->update($user, $salesReturn);
    }

    /**
     * Approve a return: the responsible branch manager of its branch/location
     * (or Super Admin). No dedicated permission is required — the gate is the
     * branch-manager relationship itself.
     */
    public function approve(User $user, SalesReturn $salesReturn): bool
    {
        return SalesScope::canApprove($user, $salesReturn);
    }

    /**
     * Reject a return: same audience as approval.
     */
    public function reject(User $user, SalesReturn $salesReturn): bool
    {
        return $this->approve($user, $salesReturn);
    }
}
