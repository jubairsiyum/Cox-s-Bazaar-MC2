<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Branch/location scoping for the sales section.
 *
 * Mirrors LocationScope's restriction rule (Outlet Managers are limited to
 * their own records while Super Admins and head-office Managers see
 * everything) but matches records on EITHER the location or the branch: a
 * user tied to a location and a user tied only to a branch are both honoured,
 * as are records that carry only one of the two.
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
     * Whether the user may act on a record of the given location/branch.
     */
    public static function canAccess(User $user, int|string|null $locationId, int|string|null $branchId = null): bool
    {
        if (! self::isRestricted($user)) {
            return true;
        }

        $locationMatch = $user->location_id !== null
            && $locationId !== null
            && (int) $user->location_id === (int) $locationId;

        // Records that only carry a location derive their branch from it.
        $branchId ??= $locationId !== null
            ? Location::query()->whereKey($locationId)->value('branch_id')
            : null;

        $branchMatch = $user->branch_id !== null
            && $branchId !== null
            && (int) $user->branch_id === (int) $branchId;

        return $locationMatch || $branchMatch;
    }

    /**
     * Whether the user may act on the given record's location/branch.
     */
    public static function canAccessRecord(User $user, Model $record): bool
    {
        return self::canAccess($user, $record->getAttribute('location_id'), $record->getAttribute('branch_id'));
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
    public static function scopeQuery(Builder $query, User $user, string $locationColumn = 'location_id', string $branchColumn = 'branch_id'): Builder
    {
        if (! self::isRestricted($user)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user, $locationColumn, $branchColumn) {
            if ($user->location_id !== null) {
                $q->orWhere($locationColumn, $user->location_id);
            }

            if ($user->branch_id !== null) {
                $q->orWhere($branchColumn, $user->branch_id);

                // Records that only carry a location derive their branch from it.
                $q->orWhereIn(
                    $locationColumn,
                    Location::query()->where('branch_id', $user->branch_id)->pluck('id')
                );
            }

            if ($user->location_id === null && $user->branch_id === null) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
