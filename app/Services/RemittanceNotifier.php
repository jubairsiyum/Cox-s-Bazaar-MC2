<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\AccountTransfer;
use App\Models\User;

/**
 * In-app notifications for the cash remittance / account transfer lifecycle:
 * - When a branch submits a cash remittance to HQ, Central HQ (Super Admins & Accountants)
 *   are alerted that a remittance awaits approval, and the creator gets a submission receipt.
 * - When Central HQ approves or rejects a remittance, the submitting branch managers
 *   and creator are notified immediately.
 */
class RemittanceNotifier
{
    /**
     * Notify Central HQ and the creator when a new cash remittance is submitted.
     */
    public static function submitted(AccountTransfer $transfer): void
    {
        $transfer->loadMissing(['location', 'fromAccount.location', 'toAccount.location', 'creator']);

        $fromBranch = $transfer->location?->name
            ?? $transfer->fromAccount?->location?->name
            ?? 'Branch';
        $amountStr = Money::format($transfer->amount);
        $creatorName = $transfer->creator?->name ?? 'Staff';
        $fromAccName = $transfer->fromAccount?->name ?? 'Cash Till';
        $toAccName = $transfer->toAccount?->name ?? 'Central HQ';
        $url = route('account-transfers.index');
        $locationId = $transfer->location_id ?? $transfer->fromAccount?->location_id;

        // 1. Notify Super Admins & Accountants at Central HQ
        $centralReviewers = User::query()->active()->role(['Super Admin', 'Accountant'])->get();
        NotificationService::notifyMany(
            $centralReviewers,
            "Cash Remittance Awaits Approval: {$fromBranch}",
            "{$creatorName} submitted a cash remittance of {$amountStr} ({$fromAccName} → {$toAccName}).",
            $url,
            'warning',
            $locationId
        );

        // 2. Notify submitting branch managers / creator
        $branchManagers = ManagerLookup::managersFor($locationId);
        if ($transfer->creator !== null && ! $branchManagers->contains('id', $transfer->creator->id)) {
            $branchManagers->push($transfer->creator);
        }

        NotificationService::notifyMany(
            $branchManagers,
            "Cash Remittance Submitted ({$amountStr})",
            "Your remittance of {$amountStr} to {$toAccName} has been queued for Central HQ approval.",
            $url,
            'info',
            $locationId
        );
    }

    /**
     * Notify branch manager and creator that the remittance was approved.
     */
    public static function approved(AccountTransfer $transfer, ?User $approver): void
    {
        $transfer->loadMissing(['location', 'fromAccount.location', 'creator']);

        $amountStr = Money::format($transfer->amount);
        $approverName = $approver?->name ?? 'Central HQ';
        $url = route('account-transfers.index');
        $locationId = $transfer->location_id ?? $transfer->fromAccount?->location_id;

        $recipients = ManagerLookup::managersFor($locationId);
        if ($transfer->creator !== null && ! $recipients->contains('id', $transfer->creator->id)) {
            $recipients->push($transfer->creator);
        }

        NotificationService::notifyMany(
            $recipients,
            "Cash Remittance Approved ({$amountStr})",
            "Your cash remittance of {$amountStr} was approved by {$approverName}. Balances have been updated.",
            $url,
            'success',
            $locationId
        );
    }

    /**
     * Notify branch manager and creator that the remittance was rejected.
     */
    public static function rejected(AccountTransfer $transfer, ?User $reviewer): void
    {
        $transfer->loadMissing(['location', 'fromAccount.location', 'creator']);

        $amountStr = Money::format($transfer->amount);
        $reviewerName = $reviewer?->name ?? 'Central HQ';
        $url = route('account-transfers.index');
        $locationId = $transfer->location_id ?? $transfer->fromAccount?->location_id;

        $recipients = ManagerLookup::managersFor($locationId);
        if ($transfer->creator !== null && ! $recipients->contains('id', $transfer->creator->id)) {
            $recipients->push($transfer->creator);
        }

        NotificationService::notifyMany(
            $recipients,
            "Cash Remittance Rejected ({$amountStr})",
            "Your cash remittance of {$amountStr} was rejected by {$reviewerName}. No funds were transferred.",
            $url,
            'error',
            $locationId
        );
    }
}
