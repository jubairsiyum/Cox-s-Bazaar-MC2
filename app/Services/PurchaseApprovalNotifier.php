<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Branch-manager-wise approval notifications for the purchasing section.
 *
 * "Managers of a branch/location" are the active users whose location_id or
 * branch_id matches — either field may be set on a user and both are honoured,
 * so outlet managers tied to a location and branch staff tied only to a branch
 * are found alike. Purchase orders and purchase returns share this helper so
 * both flows notify the same audience the same way.
 */
class PurchaseApprovalNotifier
{
    /**
     * Active users responsible for the given location and/or branch.
     *
     * Delegates to the shared {@see ManagerLookup} so every module finds the
     * same audience the same way.
     *
     * @return Collection<int, User>
     */
    public static function managersFor(int|string|null $locationId, int|string|null $branchId = null): Collection
    {
        return ManagerLookup::managersFor($locationId, $branchId);
    }

    /**
     * Tell the responsible managers that a purchase or return awaits approval.
     */
    public static function awaitingApproval(PurchaseOrder|PurchaseReturn $document): void
    {
        $creator = $document->creator;

        NotificationService::notifyMany(
            self::managersFor($document->location_id, $creator?->branch_id),
            self::title($document, 'awaits your approval'),
            sprintf(
                '%s · %s · %s, submitted by %s.',
                $document->supplier?->name ?? 'Unknown supplier',
                $document->location?->name ?? 'Unknown location',
                Money::format($document->grand_total),
                $creator?->name ?? 'Unknown user'
            ),
            self::showUrl($document),
            'warning'
        );
    }

    /**
     * Tell the creator of a purchase or return what was decided on it.
     */
    public static function decided(PurchaseOrder|PurchaseReturn $document, bool $approved): void
    {
        $creator = $document->creator;

        if ($creator === null) {
            return;
        }

        NotificationService::notify(
            $creator,
            self::title($document, $approved ? 'approved' : 'rejected'),
            sprintf(
                '%s for %s (%s) was %s by %s.',
                self::label($document),
                $document->supplier?->name ?? 'Unknown supplier',
                Money::format($document->grand_total),
                $approved ? 'approved' : 'rejected',
                $document->approver?->name ?? 'an authorized manager'
            ),
            self::showUrl($document),
            $approved ? 'success' : 'error'
        );
    }

    /**
     * Notification title for a purchase document event.
     */
    private static function title(PurchaseOrder|PurchaseReturn $document, string $event): string
    {
        return sprintf("%s '%s' %s.", self::label($document), $document->reference_no, $event);
    }

    /**
     * Human-readable document kind.
     */
    private static function label(PurchaseOrder|PurchaseReturn $document): string
    {
        return $document instanceof PurchaseOrder ? 'Purchase order' : 'Purchase return';
    }

    /**
     * Deep link to the document's detail page.
     */
    private static function showUrl(PurchaseOrder|PurchaseReturn $document): string
    {
        return $document instanceof PurchaseOrder
            ? route('purchase-invoices.show', $document)
            : route('purchase-returns.show', $document);
    }
}
