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
        // Cancelled transfers are frozen. Completed transfers may only be
        // corrected by a Super Admin so branch users cannot bypass HQ approval.
        if ($accountTransfer->status === AccountEntryStatus::Completed && ! $user->isSuperAdmin()) {
            return false;
        }

        return $accountTransfer->status !== AccountEntryStatus::Cancelled
            && $this->create($user)
            && BranchScope::canAccess($user, $accountTransfer->location_id);
    }

    public function delete(User $user, AccountTransfer $accountTransfer): bool
    {
        // Branch managers cannot delete transfer records — deletion is restricted to Super Admin
        return $user->isSuperAdmin();
    }

    public function approve(User $user, AccountTransfer $accountTransfer): bool
    {
        if ($accountTransfer->status !== AccountEntryStatus::Pending) {
            return false;
        }

        $accountTransfer->loadMissing(['fromAccount', 'toAccount']);
        $fromLocationId = $accountTransfer->fromAccount?->location_id;
        $toLocationId = $accountTransfer->toAccount?->location_id;
        $isRemittance = $fromLocationId !== $toLocationId;

        if ($isRemittance) {
            // Remittance to HQ: Destination (HQ / Super Admin) confirms receipt.
            // Branch manager (creator) CANNOT approve their own remittance.
            return (BranchScope::canAccess($user, $toLocationId) || BranchScope::isUnrestricted($user))
                && (int) $user->id !== (int) $accountTransfer->created_by
                && $user->hasAnyPermission(self::approverPermissions());
        }

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
