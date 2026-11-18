<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use App\Services\LocationScope;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock-transfers.manage');
    }

    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.manage')
            && (
                LocationScope::canAccess($user, $stockTransfer->from_location_id)
                || LocationScope::canAccess($user, $stockTransfer->to_location_id)
            );
    }

    public function create(User $user): bool
    {
        return $user->can('stock-transfers.manage');
    }

    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.manage')
            && LocationScope::canAccess($user, $stockTransfer->from_location_id);
    }

    public function delete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.manage')
            && LocationScope::canAccess($user, $stockTransfer->from_location_id);
    }

    /**
     * Approve (receive) a transfer: only the receiving side may do this.
     */
    public function approve(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.approve')
            && LocationScope::canAccess($user, $stockTransfer->to_location_id);
    }

    /**
     * Reject an incoming transfer: same audience as approval.
     */
    public function reject(User $user, StockTransfer $stockTransfer): bool
    {
        return $this->approve($user, $stockTransfer);
    }

    /**
     * Cancel a transfer: the sending side calls this off before receipt.
     */
    public function cancel(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.manage')
            && LocationScope::canAccess($user, $stockTransfer->from_location_id);
    }
}
