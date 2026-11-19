<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared manager lookup for the purchasing-adjacent modules.
 *
 * "Managers of a branch" are the active users assigned to that outlet through
 * their branch (users.branch_id points at the locations row of their outlet).
 * Purchasing itself no longer sends approval notifications: purchase documents
 * take effect the moment the responsible manager creates them. The lookup stays
 * because other modules (stock transfers, stock adjustments, sales) resolve
 * their audiences through it.
 */
class PurchaseApprovalNotifier
{
    /**
     * Active users responsible for the given location.
     *
     * Delegates to the shared {@see ManagerLookup} so every module finds the
     * same audience the same way.
     *
     * @return Collection<int, User>
     */
    public static function managersFor(int|string|null $locationId): Collection
    {
        return ManagerLookup::managersFor($locationId);
    }
}
