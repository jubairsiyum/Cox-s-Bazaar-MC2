<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branch (location) scoping for outlet-level users.
 *
 * Outlet Managers are tied to a single branch — the outlet/location they work
 * at (users.branch_id is a locations id) — and may only see and act on
 * documents of that location. Super Admins and Managers (head office) keep
 * complete visibility across all locations. Users without a branch assignment
 * are unrestricted as well.
 */
class LocationScope
{
    /**
     * Whether the user is limited to specific locations.
     */
    public static function isRestricted(User $user): bool
    {
        return $user->hasRole('Outlet Manager')
            && ! $user->hasAnyRole(['Super Admin', 'Manager']);
    }

    /**
     * Location ids the user may work with, or null when unrestricted.
     *
     * @return array<int, int>|null
     */
    public static function accessibleLocationIds(User $user): ?array
    {
        if (! self::isRestricted($user)) {
            return null;
        }

        return $user->branch_id !== null ? [(int) $user->branch_id] : [];
    }

    /**
     * Whether the user may act on a document of the given location.
     */
    public static function canAccess(User $user, int|string|null $locationId): bool
    {
        if (! self::isRestricted($user)) {
            return true;
        }

        return $locationId !== null
            && $user->branch_id !== null
            && (int) $user->branch_id === (int) $locationId;
    }

    /**
     * Restrict a query to the user's accessible locations.
     */
    public static function scopeQuery(Builder $query, User $user, string $column = 'location_id'): Builder
    {
        $ids = self::accessibleLocationIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids);
    }
}
