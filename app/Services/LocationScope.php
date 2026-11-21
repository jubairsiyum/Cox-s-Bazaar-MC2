<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branch (location) scoping facade.
 *
 * The strict branch-isolation concept lives in {@see BranchScope}: full
 * cross-branch access for the Super Admin only, own-branch matching for every
 * assigned user, and no operational data for restricted users without a
 * branch assignment. This class is a thin facade kept for existing call
 * sites; new code should use BranchScope directly.
 */
class LocationScope
{
    /**
     * Whether the user is limited to their own branch/location.
     */
    public static function isRestricted(User $user): bool
    {
        return BranchScope::isRestricted($user);
    }

    /**
     * Location ids the user may work with, or null when unrestricted.
     *
     * @return array<int, int>|null
     */
    public static function accessibleLocationIds(User $user): ?array
    {
        return BranchScope::accessibleLocationIds($user);
    }

    /**
     * Whether the user may act on a document of the given location.
     */
    public static function canAccess(User $user, int|string|null $locationId): bool
    {
        return BranchScope::canAccess($user, $locationId);
    }

    /**
     * Restrict a query to the user's accessible locations.
     */
    public static function scopeQuery(Builder $query, User $user, string $column = 'location_id'): Builder
    {
        return BranchScope::scopeQuery($query, $user, $column);
    }
}
