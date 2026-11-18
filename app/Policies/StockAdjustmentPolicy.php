<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\LocationScope;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock-adjustments.manage');
    }

    public function view(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.manage')
            && LocationScope::canAccess($user, $stockAdjustment->location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('stock-adjustments.manage');
    }

    /**
     * Only pending adjustments are editable: once approved the stock effect
     * is posted and the request becomes immutable.
     */
    public function update(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.manage')
            && LocationScope::canAccess($user, $stockAdjustment->location_id)
            && $stockAdjustment->isPending();
    }

    public function delete(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.manage')
            && LocationScope::canAccess($user, $stockAdjustment->location_id);
    }

    /**
     * Approve (apply) an adjustment: managers of the location or branch
     * where it happened, plus head-office roles.
     */
    public function approve(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.manage')
            && LocationScope::canAccess($user, $stockAdjustment->location_id);
    }

    /**
     * Reject an adjustment request: same audience as approval.
     */
    public function reject(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $this->approve($user, $stockAdjustment);
    }
}
