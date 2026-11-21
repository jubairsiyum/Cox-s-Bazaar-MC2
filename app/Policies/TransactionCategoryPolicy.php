<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TransactionCategory;
use App\Models\User;

/**
 * Reference-data screens for the Transactions area: anyone who can see the
 * finance ledger may look them up, and anyone who may record entries may
 * manage them.
 */
class TransactionCategoryPolicy
{
    /**
     * Permissions qualifying a user to manage transaction categories.
     *
     * @var array<int, string>
     */
    public const MANAGE_PERMISSIONS = ['finance.manage', 'income-transactions.manage', 'expense-transactions.manage'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(array_merge(['finance.view'], self::MANAGE_PERMISSIONS));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(self::MANAGE_PERMISSIONS);
    }

    public function update(User $user, TransactionCategory $transactionCategory): bool
    {
        return $user->hasAnyPermission(self::MANAGE_PERMISSIONS);
    }

    public function toggleStatus(User $user, TransactionCategory $transactionCategory): bool
    {
        return $user->hasAnyPermission(self::MANAGE_PERMISSIONS);
    }

    public function delete(User $user, TransactionCategory $transactionCategory): bool
    {
        return $user->hasAnyPermission(self::MANAGE_PERMISSIONS);
    }
}
