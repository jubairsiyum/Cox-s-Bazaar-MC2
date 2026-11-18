<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branch (location) scoping for outlet-level users.
 *
 * Outlet Managers are tied to a single location (or branch) and may only see
 * and act on documents of that location/branch. Super Admins and Managers
 * (head office) keep complete visibility across all locations. The branch is
 * honoured both directly (users.branch_id) and through the location's own
 * branch (locations.branch_id), so either linkage works.
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

        $ids = [];

        if ($user->location_id) {
            $ids[] = (int) $user->location_id;
        }

        if ($user->branch_id) {
            $ids = array_merge(
                $ids,
                Location::query()->where('branch_id', $user->branch_id)->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether the user may act on a document of the given location.
     */
    public static function canAccess(User $user, int|string|null $locationId): bool
    {
        if (! self::isRestricted($user)) {
            return true;
        }

        if ($locationId === null) {
            return false;
        }

        if ($user->location_id !== null && (int) $user->location_id === (int) $locationId) {
            return true;
        }

        return $user->branch_id !== null
            && Location::query()->whereKey($locationId)->where('branch_id', $user->branch_id)->exists();
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
