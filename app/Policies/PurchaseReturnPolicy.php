<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;
use App\Services\LocationScope;

class PurchaseReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-returns.manage');
    }

    public function view(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->viewAny($user) && LocationScope::canAccess($user, $purchaseReturn->location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-returns.manage');
    }

    public function update(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase-returns.manage')
            && LocationScope::canAccess($user, $purchaseReturn->location_id);
    }

    public function delete(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->update($user, $purchaseReturn);
    }

    /**
     * Approve a return: the responsible manager of its location.
     */
    public function approve(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase-returns.approve')
            && LocationScope::canAccess($user, $purchaseReturn->location_id);
    }

    /**
     * Reject a return: same audience as approval.
     */
    public function reject(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->approve($user, $purchaseReturn);
    }
}
