<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AccountEntryStatus;
use App\Enums\AccountTransactionType;
use App\Models\AccountTransaction;
use App\Models\User;
use App\Services\BranchScope;
use App\Services\FinanceApprovals;

/**
 * Income/expense entries. Every decision is additionally branch-scoped:
 * users may only act on entries stamped with their own branch (strict branch
 * isolation — see BranchScope).
 */
class AccountTransactionPolicy
{
    /**
     * Permissions qualifying a user to approve/reject a given entry type.
     *
     * @return array<int, string>
     */
    public static function approverPermissions(AccountTransactionType|string|null $type): array
    {
        $value = $type instanceof AccountTransactionType ? $type->value : $type;

        $permissions = ['finance.manage'];

        if ($value !== 'expense') {
            $permissions[] = 'income-transactions.manage';
        }

        if ($value !== 'income') {
            $permissions[] = 'expense-transactions.manage';
        }

        return $permissions;
    }

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Outlet Manager') && ! $user->hasAnyPermission(['finance.manage', 'income-transactions.manage', 'expense-transactions.manage'])) {
            return false;
        }

        return $user->hasAnyPermission([
            'finance.view', 'finance.manage', 'income-transactions.manage', 'expense-transactions.manage',
        ]);
    }

    /**
     * Create an income or expense entry: the type decides which manage
     * permission is required (finance.manage covers both).
     */
    public function create(User $user, AccountTransactionType|string|null $type = null): bool
    {
        return $this->canManage($user, $type);
    }

    public function update(User $user, AccountTransaction $accountTransaction): bool
    {
        // Cancelled entries are frozen. Completed entries may only be edited
        // by a Super Admin so branch users cannot alter approved amounts.
        if ($accountTransaction->status === AccountEntryStatus::Completed && ! $user->isSuperAdmin()) {
            return false;
        }

        return $accountTransaction->status !== AccountEntryStatus::Cancelled
            && $this->canManage($user, $accountTransaction->type)
            && BranchScope::canAccess($user, $accountTransaction->location_id);
    }

    public function delete(User $user, AccountTransaction $accountTransaction): bool
    {
        if ($accountTransaction->status === AccountEntryStatus::Completed && ! $user->isSuperAdmin()) {
            return false;
        }

        return $this->canManage($user, $accountTransaction->type)
            && BranchScope::canAccess($user, $accountTransaction->location_id);
    }

    public function approve(User $user, AccountTransaction $accountTransaction): bool
    {
        return BranchScope::canAccess($user, $accountTransaction->location_id)
            && FinanceApprovals::canDecide(
                $user,
                self::approverPermissions($accountTransaction->type),
                $accountTransaction->created_by,
                $accountTransaction->location_id
            );
    }

    public function reject(User $user, AccountTransaction $accountTransaction): bool
    {
        return $this->approve($user, $accountTransaction);
    }

    private function canManage(User $user, AccountTransactionType|string|null $type): bool
    {
        if ($user->can('finance.manage')) {
            return true;
        }

        $value = $type instanceof AccountTransactionType ? $type->value : $type;

        return match ($value) {
            'income' => $user->can('income-transactions.manage'),
            'expense' => $user->can('expense-transactions.manage'),
            default => $user->hasAnyPermission(['income-transactions.manage', 'expense-transactions.manage']),
        };
    }
}
