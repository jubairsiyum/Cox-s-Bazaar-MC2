<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Approval rules for finance entries (account transactions and transfers):
 * a user holding one of the qualifying permissions may decide on entries, but
 * nobody may approve their own entry while another qualified approver exists.
 */
class FinanceApprovals
{
    /**
     * Whether the user may approve/reject an entry created by the given user.
     *
     * @param  array<int, string>  $permissions  Permissions qualifying an approver.
     */
    public static function canDecide(User $user, array $permissions, ?int $createdById): bool
    {
        if (! $user->hasAnyPermission($permissions)) {
            return false;
        }

        if ((int) $createdById !== (int) $user->id) {
            return true;
        }

        // Self-approval is only tolerated when nobody else could decide.
        return ! self::hasOtherApprover($user, $permissions);
    }

    /**
     * Whether an active user other than the given one holds any of the
     * qualifying permissions (directly or through a role).
     *
     * @param  array<int, string>  $permissions
     */
    public static function hasOtherApprover(User $user, array $permissions): bool
    {
        return User::query()
            ->whereKeyNot($user->getKey())
            ->where('status', 'active')
            ->get()
            ->contains(fn (User $candidate): bool => $candidate->hasAnyPermission($permissions));
    }
}
