<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Branch (location) scoping for the sales section.
 *
 * Mirrors LocationScope's restriction rule (Outlet Managers are limited to
 * their own records while Super Admins and head-office Managers see
 * everything). A user's branch IS their assigned outlet/location, so records
 * are matched on their location only; the legacy branches-table matching is
 * gone.
 */
class SalesScope
{
    /**
     * Whether the user is limited to their own branch/location records.
     */
    public static function isRestricted(User $user): bool
    {
        return LocationScope::isRestricted($user);
    }

    /**
     * Whether the user may act on a record of the given location.
     */
    public static function canAccess(User $user, int|string|null $locationId): bool
    {
        return LocationScope::canAccess($user, $locationId);
    }

    /**
     * Whether the user may act on the given record's location.
     */
    public static function canAccessRecord(User $user, Model $record): bool
    {
        return self::canAccess($user, $record->getAttribute('location_id'));
    }

    /**
     * Approvals are reserved for the responsible branch manager (or Super
     * Admin): a manager-role user whose branch/location matches the record.
     * Head-office Managers are unrestricted and thereby responsible for all.
     */
    public static function canApprove(User $user, Model $record): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole(['Manager', 'Outlet Manager'])
            && self::canAccessRecord($user, $record);
    }

    /**
     * Restrict a query to the user's accessible branch/location records.
     */
    public static function scopeQuery(Builder $query, User $user, string $locationColumn = 'location_id'): Builder
    {
        return LocationScope::scopeQuery($query, $user, $locationColumn);
    }
}
