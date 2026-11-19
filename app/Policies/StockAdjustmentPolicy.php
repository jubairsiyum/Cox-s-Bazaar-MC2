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
     * Applied adjustments are editable while their stock effect can still be
     * reversed safely; the controller enforces the state guard with a friendly
     * message. There is no approval step anymore.
     */
    public function update(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.manage')
            && LocationScope::canAccess($user, $stockAdjustment->location_id);
    }

    public function delete(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.manage')
            && LocationScope::canAccess($user, $stockAdjustment->location_id);
    }
}
