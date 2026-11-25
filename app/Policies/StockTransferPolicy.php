<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\BranchScope;
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

    /**
     * Request stock from another location: any branch-assigned user may ask.
     * The requesting location is always the user's own branch.
     */
    public function createRequest(User $user): bool
    {
        return $user->can('stock-transfers.manage')
            && BranchScope::locationId($user) !== null;
    }

    /**
     * Stock requests are decided by the source, never edited in place.
     */
    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        return ! $stockTransfer->is_request
            && $stockTransfer->status !== StockTransferStatus::Received
            && $user->can('stock-transfers.manage')
            && LocationScope::canAccess($user, $stockTransfer->from_location_id);
    }

    public function delete(User $user, StockTransfer $stockTransfer): bool
    {
        return ! in_array($stockTransfer->status, [StockTransferStatus::Requested, StockTransferStatus::InTransit, StockTransferStatus::Received], true)
            && $user->can('stock-transfers.manage')
            && LocationScope::canAccess($user, $stockTransfer->from_location_id);
    }

    public function dispatch(User $user, StockTransfer $stockTransfer): bool
    {
        return in_array($stockTransfer->status, [StockTransferStatus::Draft, StockTransferStatus::Requested], true)
            && $user->can('stock-transfers.manage')
            && LocationScope::canAccess($user, $stockTransfer->from_location_id);
    }

    /**
     * Approve (receive) a transfer. A plain transfer is received by the
     * receiving side; a stock request is approved by the source it was made to,
     * and never by the branch that asked for it.
     */
    public function approve(User $user, StockTransfer $stockTransfer): bool
    {
        if ($stockTransfer->status === StockTransferStatus::InTransit) {
            return $user->can('stock-transfers.approve')
                && LocationScope::canAccess($user, $stockTransfer->to_location_id);
        }

        if ($stockTransfer->is_request) {
            return $user->can('stock-transfers.approve')
                && LocationScope::canAccess($user, $stockTransfer->from_location_id)
                && $stockTransfer->created_by !== $user->id;
        }

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
     * Withdraw a pending stock request: only the branch that raised it, while
     * the source has not decided yet.
     */
    public function withdraw(User $user, StockTransfer $stockTransfer): bool
    {
        return $stockTransfer->is_request
            && $stockTransfer->status === StockTransferStatus::Requested
            && $stockTransfer->created_by === $user->id;
    }

    /**
     * Cancel a transfer: the sending side calls this off before receipt.
     */
    public function cancel(User $user, StockTransfer $stockTransfer): bool
    {
        return in_array($stockTransfer->status, [StockTransferStatus::Draft, StockTransferStatus::InTransit], true)
            && $user->can('stock-transfers.manage')
            && LocationScope::canAccess($user, $stockTransfer->from_location_id);
    }
}
