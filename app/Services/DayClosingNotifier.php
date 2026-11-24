<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DayClosing;
use App\Models\User;

/**
 * In-app alerts for the daily cash close & period lock lifecycle:
 * - When a branch closes its day, Super Admins are notified with variance audit details,
 *   and branch managers receive a confirmation that transactions are period-locked.
 * - When Super Admin reopens a closed day, the branch managers and original closer
 *   are notified that transactions have been unlocked.
 *
 * Every notification is tagged with location_id so strict branch isolation
 * (NotificationService::scopeFor) correctly routes branch alerts to the right audience.
 */
class DayClosingNotifier
{
    /**
     * Notify Super Admins and branch managers that a branch has closed its business day.
     */
    public static function closed(DayClosing $closing): void
    {
        $closing->loadMissing(['location', 'closedBy']);

        $branchName = $closing->location?->name ?? 'Branch';
        $closingDate = $closing->closing_date->format('d M Y');
        $closerName = $closing->closedBy?->name ?? 'Staff';
        $variance = (float) $closing->variance;
        $countedCash = number_format((float) $closing->counted_cash, 2);
        $systemCash = number_format((float) $closing->system_cash, 2);

        $varianceText = match ($closing->variance_status) {
            'exact' => 'exact zero variance',
            'minor' => 'minor variance of '.($variance > 0 ? '+' : '').'৳'.number_format($variance, 2),
            default => 'significant discrepancy of '.($variance > 0 ? '+' : '').'৳'.number_format($variance, 2),
        };

        $adminType = $closing->variance_status === 'significant' ? 'warning' : 'success';
        $adminTitle = match ($closing->variance_status) {
            'exact' => "Daily Cash Closed: {$branchName}",
            'minor' => "Daily Cash Closed: {$branchName} (Minor Variance)",
            default => "Daily Cash Close Alert: {$branchName} (Significant Discrepancy)",
        };

        $adminBody = "{$closerName} closed {$branchName} for {$closingDate}. Counted: ৳{$countedCash}, Expected: ৳{$systemCash} ({$varianceText}). Transactions are now locked.";

        // 1. Notify Super Admins (management audit)
        $superAdmins = User::query()->active()->role('Super Admin')->get();
        NotificationService::notifyMany(
            $superAdmins,
            $adminTitle,
            $adminBody,
            route('day-closings.index'),
            $adminType,
            $closing->location_id
        );

        // 2. Notify Branch Managers and closer
        $branchManagers = ManagerLookup::managersFor($closing->location_id);
        if ($closing->closedBy !== null && ! $branchManagers->contains('id', $closing->closedBy->id)) {
            $branchManagers->push($closing->closedBy);
        }

        NotificationService::notifyMany(
            $branchManagers,
            "Day Closed: {$branchName}",
            "Business day for {$closingDate} was closed by {$closerName}. Counted cash: ৳{$countedCash} ({$varianceText}). Period locked.",
            route('day-closings.index'),
            $adminType,
            $closing->location_id
        );
    }

    /**
     * Notify branch managers and original closer that a business day has been reopened.
     */
    public static function reopened(DayClosing $closing, User $reopenedBy, string $reason): void
    {
        $closing->loadMissing(['location', 'closedBy']);

        $branchName = $closing->location?->name ?? 'Branch';
        $closingDate = $closing->closing_date->format('d M Y');
        $adminName = $reopenedBy->name;

        $recipients = ManagerLookup::managersFor($closing->location_id);
        if ($closing->closedBy !== null && ! $recipients->contains('id', $closing->closedBy->id)) {
            $recipients->push($closing->closedBy);
        }

        NotificationService::notifyMany(
            $recipients,
            "Day Reopened: {$branchName}",
            "Business day for {$closingDate} was reopened and unlocked by Super Admin {$adminName}. Reason: {$reason}.",
            route('day-closings.index'),
            'warning',
            $closing->location_id
        );
    }
}
