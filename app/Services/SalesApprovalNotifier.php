<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\SalesReturn;

/**
 * Branch-manager-wise approval notifications for the sales section.
 *
 * The audience is resolved through PurchaseApprovalNotifier::managersFor(),
 * which finds the active users assigned to the return's outlet. The shared
 * notification vocabulary is used throughout: warning for pending approval,
 * success for approved, error for rejected.
 */
class SalesApprovalNotifier
{
    /**
     * Tell the responsible branch managers that a sales return awaits approval.
     */
    public static function awaitingApproval(SalesReturn $return): void
    {
        $creator = $return->creator;

        NotificationService::notifyMany(
            PurchaseApprovalNotifier::managersFor($return->location_id),
            self::title($return, 'awaits your approval'),
            sprintf(
                '%s · %s · %s, submitted by %s.',
                $return->customer?->name ?? 'Walk-in customer',
                $return->location?->name ?? 'Unknown location',
                Money::format($return->refund_amount),
                $creator?->name ?? 'Unknown user'
            ),
            route('sales-returns.show', $return),
            'warning'
        );
    }

    /**
     * Tell the creator of a sales return what was decided on it.
     */
    public static function decided(SalesReturn $return, bool $approved): void
    {
        $creator = $return->creator;

        if ($creator === null) {
            return;
        }

        NotificationService::notify(
            $creator,
            self::title($return, $approved ? 'approved' : 'rejected'),
            sprintf(
                'Sales return for %s (%s) was %s by %s.',
                $return->customer?->name ?? 'Walk-in customer',
                Money::format($return->refund_amount),
                $approved ? 'approved' : 'rejected',
                $return->approver?->name ?? 'an authorized manager'
            ),
            route('sales-returns.show', $return),
            $approved ? 'success' : 'error'
        );
    }

    /**
     * Notification title for a sales return event.
     */
    private static function title(SalesReturn $return, string $event): string
    {
        return sprintf("Sales return '%s' %s.", $return->return_no, $event);
    }
}
