<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared lookup for the users responsible for a branch (outlet location).
 *
 * "Managers of a branch" are the active users assigned to that outlet: their
 * branch (users.branch_id) is the locations row of the outlet they work at.
 * The legacy branches-table matching is gone — a user's branch IS their
 * assigned outlet.
 */
class ManagerLookup
{
    /**
     * Active users responsible for the given location.
     *
     * @return Collection<int, User>
     */
    public static function managersFor(int|string|null $locationId): Collection
    {
        if ($locationId === null) {
            return collect();
        }

        return User::query()
            ->active()
            ->where('branch_id', (int) $locationId)
            ->get();
    }
}
