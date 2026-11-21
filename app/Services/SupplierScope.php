<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * SUPPLIER ISOLATION — the single scope mechanism for supplier portal logins.
 *
 * A Supplier-role login (users.id → suppliers.user_id) may only see its own
 * supplier record and the purchasing data of that supplier: its profile,
 * purchase orders, purchase payments, purchase returns and dues. Admin and
 * Super Admin users are never narrowed here; they keep the branch rules of
 * BranchScope / LocationScope.
 *
 * A supplier login without a linked supplier record sees nothing at all.
 */
class SupplierScope
{
    /**
     * Restrict a query to the supplier linked to the user (no-op for everyone else).
     */
    public static function scopeQuery(Builder $query, User $user, string $column = 'supplier_id'): Builder
    {
        if (! $user->isSupplierPortalUser()) {
            return $query;
        }

        $ownSupplierId = self::ownSupplierId($user);

        return $ownSupplierId === null
            ? $query->whereRaw('1 = 0')
            : $query->where($column, $ownSupplierId);
    }

    /**
     * Restrict a query whose supplier lives on a related record (purchase
     * payments belong to a supplier through their purchase order).
     */
    public static function scopeQueryHas(Builder $query, User $user, string $relation, string $column = 'supplier_id'): Builder
    {
        if (! $user->isSupplierPortalUser()) {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $inner) => self::scopeQuery($inner, $user, $column));
    }

    /**
     * Whether the user may see/act on data belonging to the given supplier.
     */
    public static function canAccess(User $user, int|string|null $supplierId): bool
    {
        if (! $user->isSupplierPortalUser()) {
            return true;
        }

        $ownSupplierId = self::ownSupplierId($user);

        return $ownSupplierId !== null
            && $supplierId !== null
            && (int) $supplierId === $ownSupplierId;
    }

    /**
     * The supplier record linked to a supplier login, or null when none is linked.
     */
    public static function ownSupplierId(User $user): ?int
    {
        return $user->supplier?->id;
    }
}
