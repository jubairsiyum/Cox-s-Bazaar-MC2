<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Reference-data screens for Finance payment methods: anyone who can see the
 * finance ledger may look them up, and anyone who may record entries may
 * manage them.
 */
class PaymentMethodPolicy
{
    /**
     * Permissions qualifying a user to manage payment methods.
     *
     * @var array<int, string>
     */
    public const MANAGE_PERMISSIONS = ['finance.manage', 'income-transactions.manage', 'expense-transactions.manage'];

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Outlet Manager') && ! $user->hasAnyPermission(self::MANAGE_PERMISSIONS)) {
            return false;
        }

        return $user->hasAnyPermission(array_merge(['finance.view'], self::MANAGE_PERMISSIONS));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(self::MANAGE_PERMISSIONS);
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasAnyPermission(self::MANAGE_PERMISSIONS);
    }

    public function toggleStatus(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasAnyPermission(self::MANAGE_PERMISSIONS);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasAnyPermission(self::MANAGE_PERMISSIONS);
    }
}
