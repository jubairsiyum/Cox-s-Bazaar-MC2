<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * STRICT BRANCH ISOLATION — the single scope mechanism for the whole system.
 *
 * A user's Branch is the single location (outlet) they are assigned to
 * (users.branch_id → locations.id). Every operational record — inventory,
 * products availability, dashboard figures, reports, finance, purchases,
 * sales, notifications — belongs to exactly one branch and is stamped with
 * its location_id. This helper answers the only two questions that matter:
 *
 *  - "which locations may this user see?" (accessibleLocationIds / scopeQuery)
 *  - "may this user act on this record?" (canAccess / canAccessRecord)
 *
 * The rules, in full:
 *
 *  1. ONLY the Super Admin sees everything. `isUnrestricted()` is true for
 *     the Super Admin role and nobody else — role names like "Manager" or
 *     "Accountant" grant permissions, never cross-branch visibility.
 *  2. Every other user — regardless of role — is restricted to the single
 *     branch they are assigned to. Queries are filtered to that location and
 *     record-level checks compare against it, so URL tampering
 *     (?branch_id=, ?location_id=, …) can never widen the scope: callers
 *     apply the user scope first and ignore request filters for restricted
 *     users.
 *  3. A restricted user WITHOUT a branch assignment sees and can do nothing
 *     operational until an administrator assigns one. `accessibleLocationIds()`
 *     returns an empty list for them and `canAccess()` is always false.
 *
 * `LocationScope` and `SalesScope` are thin facades over this class kept for
 * the call sites that already speak those names; new code should use
 * BranchScope directly.
 */
class BranchScope
{
    /**
     * Whether the user has unrestricted cross-branch access (Super Admin only).
     */
    public static function isUnrestricted(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Whether the user is limited to their own branch (everyone but the
     * Super Admin — including users with no branch assignment at all).
     */
    public static function isRestricted(User $user): bool
    {
        return ! self::isUnrestricted($user);
    }

    /**
     * The single location id the user is assigned to, or null when unassigned.
     */
    public static function locationId(User $user): ?int
    {
        return $user->branch_id !== null ? (int) $user->branch_id : null;
    }

    /**
     * Location ids the user may work with, or null when unrestricted.
     *
     * Restricted users get their single branch id — or an empty list when no
     * branch is assigned yet, which matches (and therefore shows) nothing.
     *
     * @return array<int, int>|null
     */
    public static function accessibleLocationIds(User $user): ?array
    {
        if (self::isUnrestricted($user)) {
            return null;
        }

        return self::locationId($user) !== null ? [self::locationId($user)] : [];
    }

    /**
     * Whether the user may see/act on a record of the given location.
     */
    public static function canAccess(User $user, int|string|null $locationId): bool
    {
        if (self::isUnrestricted($user)) {
            return true;
        }

        return $locationId !== null
            && self::locationId($user) !== null
            && self::locationId($user) === (int) $locationId;
    }

    /**
     * Whether the user may see/act on the given record's location.
     */
    public static function canAccessRecord(User $user, Model $record): bool
    {
        return self::canAccess($user, $record->getAttribute('location_id'));
    }

    /**
     * Restrict a query to the user's accessible locations.
     *
     * The Super Admin's query passes through untouched. Restricted users get
     * `whereIn($column, [their branch])` — or an empty `whereIn` that matches
     * nothing when no branch is assigned.
     */
    public static function scopeQuery(Builder $query, User $user, string $column = 'location_id'): Builder
    {
        $ids = self::accessibleLocationIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids);
    }

    /**
     * Restrict a query to the user's branch plus the company-wide records that
     * carry no branch (shared cash and bank accounts). The Super Admin's query
     * passes through untouched.
     */
    public static function scopeQueryWithShared(Builder $query, User $user, string $column = 'location_id'): Builder
    {
        $ids = self::accessibleLocationIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($ids, $column): void {
            $inner->whereNull($column)->orWhereIn($column, $ids);
        });
    }

    /**
     * Restrict a query whose records can live at either of several location
     * columns (stock transfers: from_location_id OR to_location_id).
     */
    public static function scopeQueryAny(Builder $query, User $user, string ...$columns): Builder
    {
        $ids = self::accessibleLocationIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($ids, $columns): void {
            foreach ($columns as $column) {
                $inner->orWhereIn($column, $ids);
            }
        });
    }

    /**
     * Restrict a query whose branch lives on a related record (supplier
     * payments are stamped with their purchase order's location).
     */
    public static function scopeQueryHas(Builder $query, User $user, string $relation, string $column = 'location_id'): Builder
    {
        $ids = self::accessibleLocationIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $inner) => $inner->whereIn($column, $ids));
    }
}
