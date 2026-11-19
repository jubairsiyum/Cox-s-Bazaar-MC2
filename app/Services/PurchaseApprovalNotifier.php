<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared manager lookup for the purchasing-adjacent modules.
 *
 * "Managers of a branch/location" are the active users whose location_id or
 * branch_id matches — either field may be set on a user and both are honoured,
 * so outlet managers tied to a location and branch staff tied only to a branch
 * are found alike. Purchasing itself no longer sends approval notifications:
 * purchase documents take effect the moment the responsible manager creates
 * them. The lookup stays because other modules (stock transfers, stock
 * adjustments, sales) resolve their audiences through it.
 */
class PurchaseApprovalNotifier
{
    /**
     * Active users responsible for the given location and/or branch.
     *
     * Delegates to the shared {@see ManagerLookup} so every module finds the
     * same audience the same way.
     *
     * @return Collection<int, User>
     */
    public static function managersFor(int|string|null $locationId, int|string|null $branchId = null): Collection
    {
        return ManagerLookup::managersFor($locationId, $branchId);
    }
}
