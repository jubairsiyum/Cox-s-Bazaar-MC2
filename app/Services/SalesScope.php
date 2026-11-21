<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Branch (location) scoping facade for the sales section.
 *
 * The strict branch-isolation concept lives in {@see BranchScope}: full
 * cross-branch access for the Super Admin only, own-branch matching for every
 * assigned user, and no operational data for restricted users without a
 * branch assignment. This class is a thin facade kept for existing call
 * sites; new code should use BranchScope directly.
 */
class SalesScope
{
    /**
     * Whether the user is limited to their own branch/location records.
     */
    public static function isRestricted(User $user): bool
    {
        return BranchScope::isRestricted($user);
    }

    /**
     * Whether the user may act on a record of the given location.
     */
    public static function canAccess(User $user, int|string|null $locationId): bool
    {
        return BranchScope::canAccess($user, $locationId);
    }

    /**
     * Whether the user may act on the given record's location.
     */
    public static function canAccessRecord(User $user, Model $record): bool
    {
        return BranchScope::canAccessRecord($user, $record);
    }

    /**
     * Approvals are reserved for the responsible branch manager (or Super
     * Admin): a manager-role user whose branch/location matches the record.
     * Under strict branch isolation nobody else may sign off across branches —
     * the head office sees everything through the Super Admin account.
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
        return BranchScope::scopeQuery($query, $user, $locationColumn);
    }
}
