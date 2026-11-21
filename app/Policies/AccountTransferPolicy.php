<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AccountEntryStatus;
use App\Models\AccountTransfer;
use App\Models\User;
use App\Services\BranchScope;
use App\Services\FinanceApprovals;

class AccountTransferPolicy
{
    /**
     * Permissions qualifying a user to approve/reject a transfer.
     *
     * @return array<int, string>
     */
    public static function approverPermissions(): array
    {
        return ['finance.manage', 'account-transfers.manage'];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'finance.view', 'finance.manage', 'account-transfers.manage',
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['finance.manage', 'account-transfers.manage']);
    }

    public function update(User $user, AccountTransfer $accountTransfer): bool
    {
        // Cancelled transfers are frozen; completed ones are editable but their
        // balance effect is reversed and re-applied by the service layer.
        return $accountTransfer->status !== AccountEntryStatus::Cancelled
            && $this->create($user)
            && BranchScope::canAccess($user, $accountTransfer->location_id);
    }

    public function delete(User $user, AccountTransfer $accountTransfer): bool
    {
        return $this->create($user)
            && BranchScope::canAccess($user, $accountTransfer->location_id);
    }

    public function approve(User $user, AccountTransfer $accountTransfer): bool
    {
        return BranchScope::canAccess($user, $accountTransfer->location_id)
            && FinanceApprovals::canDecide(
                $user,
                self::approverPermissions(),
                $accountTransfer->created_by,
                $accountTransfer->location_id
            );
    }

    public function reject(User $user, AccountTransfer $accountTransfer): bool
    {
        return $this->approve($user, $accountTransfer);
    }
}
