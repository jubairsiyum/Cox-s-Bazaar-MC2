<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Outlet Manager') && ! $user->hasAnyPermission(['finance.manage', 'cash-accounts.manage', 'bank-accounts.manage'])) {
            return false;
        }

        return $user->hasAnyPermission([
            'finance.view', 'finance.manage', 'cash-accounts.manage', 'bank-accounts.manage',
        ]);
    }

    /**
     * Create a cash or bank account: the type decides which manage permission
     * is required (finance.manage covers both).
     */
    public function create(User $user, AccountType|string|null $type = null): bool
    {
        return $this->canManage($user, $type);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->canManage($user, $account->type);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->update($user, $account);
    }

    private function canManage(User $user, AccountType|string|null $type): bool
    {
        if ($user->can('finance.manage')) {
            return true;
        }

        $value = $type instanceof AccountType ? $type->value : $type;

        return match ($value) {
            'cash' => $user->can('cash-accounts.manage'),
            'bank' => $user->can('bank-accounts.manage'),
            default => $user->hasAnyPermission(['cash-accounts.manage', 'bank-accounts.manage']),
        };
    }
}
