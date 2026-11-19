<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared lookup for the managers responsible for a branch/location.
 *
 * "Managers of a branch/location" are the active users whose location_id or
 * branch_id matches — either field may be set on a user and both are honoured,
 * so outlet managers tied to a location and branch staff tied only to a branch
 * are found alike. When only a location is known, its branch is derived via
 * locations.branch_id so branch staff are still reached.
 */
class ManagerLookup
{
    /**
     * Active users responsible for the given location and/or branch.
     *
     * @return Collection<int, User>
     */
    public static function managersFor(int|string|null $locationId, int|string|null $branchId = null): Collection
    {
        $locationId = $locationId !== null ? (int) $locationId : null;
        $branchId = $branchId !== null ? (int) $branchId : null;

        if ($branchId === null && $locationId !== null) {
            $branchId = Location::query()->whereKey($locationId)->value('branch_id');
            $branchId = $branchId !== null ? (int) $branchId : null;
        }

        if ($locationId === null && $branchId === null) {
            return collect();
        }

        return User::query()
            ->active()
            ->where(function (Builder $query) use ($locationId, $branchId) {
                if ($locationId !== null) {
                    $query->orWhere('location_id', $locationId);
                }

                if ($branchId !== null) {
                    $query->orWhere('branch_id', $branchId);
                }
            })
            ->get();
    }
}
