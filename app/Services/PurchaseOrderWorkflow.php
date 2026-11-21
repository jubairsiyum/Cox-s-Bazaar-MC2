<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierPaymentRequestStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\User;

/**
 * The purchase order state machine, shared by every path that moves an order.
 *
 * Two coupled sides advance together:
 *  - the branch side (purchase_status): pending → ordered → received
 *    (+ cancelled). "Pending" is a derived display state: an order placed
 *    with the supplier (purchase_status "ordered", supplier_status "pending")
 *    shows as "Pending" until the supplier accepts it, then "Ordered";
 *  - the supplier side (supplier_status): pending → accepted → delivered,
 *    with accepted_at / delivered_at / received_at timestamps.
 *
 * Rules:
 *  - The supplier accepting an order moves the branch side to Ordered.
 *  - The branch marking the goods received (the "Mark Received" button on the
 *    invoice page or the edit form status "Received") moves the branch side
 *    to Received, regardless of payment.
 *  - Payments never move the branch side.
 *  - The supplier side becomes Delivered only when the order is accepted, the
 *    branch side is Received AND the order is fully paid. Whichever of
 *    receipt or full payment happens last sets Delivered. Reversing either
 *    one drops Delivered back to Accepted.
 *
 * Every transition funnels through transitionTo(), which posts/reverses stock
 * via PurchaseOrderService::applyStatusChange().
 */
class PurchaseOrderWorkflow
{
    /**
     * Initialize the supplier side of a freshly created order and announce
     * the order to the supplier's profile feed. An order created as "Received"
     * is a direct receipt of goods already taken, so the supplier side starts
     * accepted; anything else waits for the supplier.
     */
    public static function orderPlaced(PurchaseOrder $order, ?User $actor = null): void
    {
        if ($order->purchase_status->hasReceived()) {
            $order->supplier_status = SupplierOrderStatus::Accepted;
            $order->accepted_at ??= now();
        } else {
            $order->supplier_status = SupplierOrderStatus::Pending;
        }

        $order->save();

        PurchaseOrderService::applyStatusChange($order, null, $order->purchase_status);
        self::syncSupplierDelivery($order);

        if ($order->purchase_status !== PurchaseStatus::Cancelled) {
            SupplierNotifier::orderPlaced($order);
        }
    }

    /**
     * Supplier acceptance recorded on the supplier's profile: the branch side
     * dynamically flips from "Pending" to "Ordered". An order the branch has
     * already marked received is delivered as soon as it is accepted.
     */
    public static function recordAcceptance(PurchaseOrder $order, ?User $actor = null): void
    {
        if ($order->purchase_status === PurchaseStatus::Cancelled
            || $order->supplier_status !== SupplierOrderStatus::Pending
        ) {
            return;
        }

        $order->supplier_status = SupplierOrderStatus::Accepted;
        $order->accepted_at ??= now();

        // Legacy rows that still store "pending" move on to "ordered" so the
        // manager side reads "Ordered" the moment the supplier accepts. Neither
        // status holds stock, so no stock movement is involved.
        if ($order->purchase_status === PurchaseStatus::Pending) {
            $order->purchase_status = PurchaseStatus::Ordered;
        }

        $order->save();

        self::syncSupplierDelivery($order);
    }

    /**
     * A payment was applied to an order (Purchase Dues or Finance supplier
     * payments). The payment moves neither side of the order; it only settles
     * the money. Every applied payment lands on the supplier's feed.
     */
    public static function paymentApplied(PurchasePayment $payment, ?User $actor = null): void
    {
        $order = $payment->purchaseOrder?->refresh();

        if ($order !== null) {
            self::syncSupplierDelivery($order);
        }

        if ($order?->supplier !== null) {
            SupplierNotifier::purchasePaymentRecorded($payment, $actor);
        }
    }

    /**
     * A previously applied payment was reversed: the due it settled is raised
     * again, the supplier side reverts to Accepted if it was Delivered, and the
     * supplier's feed says so. The branch side never moves.
     */
    public static function paymentReversed(PurchasePayment $payment, ?User $actor = null): void
    {
        $order = $payment->purchaseOrder?->refresh();

        if ($order !== null) {
            self::syncSupplierDelivery($order);
        }

        if ($order?->supplier !== null) {
            SupplierNotifier::purchasePaymentReversed($payment, $actor);
        }
    }

    /**
     * The supplier's due moved because of an order edit, approval, or
     * cancellation. Cancelled orders carry no due, so their only notice is the
     * withdrawal itself, sent once — on the transition into Cancelled.
     */
    public static function dueAdjusted(PurchaseOrder $order, float $change, bool $wasCancelled, ?User $actor = null): void
    {
        if ($order->purchase_status === PurchaseStatus::Cancelled) {
            if (! $wasCancelled) {
                SupplierNotifier::orderWithdrawn($order, -$change);
            }

            return;
        }

        SupplierNotifier::orderDueChanged($order, $change, $actor);
    }

    /**
     * Move an order to the given manager-facing state, syncing the supplier
     * side, posting/reversing stock and retiring any open supplier payment
     * requests on cancellation. The single choke point for every transition.
     */
    public static function transitionTo(PurchaseOrder $order, PurchaseStatus $target, ?User $actor = null): void
    {
        $from = $order->purchase_status;

        $order->purchase_status = $target;

        if ($target->hasReceived()) {
            $order->received_at ??= now();
        } elseif ($from->hasReceived()) {
            $order->received_at = null;
        }

        $order->save();

        PurchaseOrderService::applyStatusChange($order, $from, $target);
        self::syncSupplierDelivery($order);

        if ($target === PurchaseStatus::Cancelled) {
            self::cancelOpenPaymentRequests($order);
        }
    }

    /**
     * Align the supplier side with the delivery condition: an accepted order
     * is Delivered once the branch has received it AND it is fully paid, and
     * reverts to Accepted when either is reversed. An order the supplier has
     * not accepted stays Pending.
     */
    public static function syncSupplierDelivery(PurchaseOrder $order): void
    {
        if ($order->supplier_status === SupplierOrderStatus::Pending) {
            return;
        }

        $fullyPaid = round((float) $order->paid_amount, 2) >= round((float) $order->grand_total, 2);
        $delivered = $order->purchase_status->hasReceived() && $fullyPaid;

        if ($delivered && $order->supplier_status !== SupplierOrderStatus::Delivered) {
            $order->supplier_status = SupplierOrderStatus::Delivered;
            $order->delivered_at = now();
        } elseif (! $delivered && $order->supplier_status === SupplierOrderStatus::Delivered) {
            $order->supplier_status = SupplierOrderStatus::Accepted;
            $order->delivered_at = null;
        } else {
            return;
        }

        $order->save();
    }

    /**
     * Withdraw the open supplier payment requests of an order once it is
     * cancelled or deleted — a cancelled purchase carries no due to clear.
     */
    public static function cancelOpenPaymentRequests(PurchaseOrder $order): void
    {
        $order->paymentRequests()
            ->where('status', SupplierPaymentRequestStatus::Open->value)
            ->update(['status' => SupplierPaymentRequestStatus::Cancelled->value]);
    }
}
