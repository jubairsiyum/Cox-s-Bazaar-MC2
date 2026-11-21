<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierOpeningPayment;
use App\Models\User;

/**
 * Supplier-side activity feed notifications.
 *
 * Suppliers have no logins — their "profile" is a page inside this admin app
 * that staff open on the supplier's behalf, and the feed is built from
 * database notifications addressed to the supplier row itself (mirrored to a
 * linked Supplier login's bell). Every event that brings a due to the supplier
 * or moves it — a new purchase, an edit that changes the amount, a payment, a
 * payment reversal, an approved return credit or a cancellation — lands there
 * with the branch, the reference, the amount and the branch-wise cleared /
 * still owing summary as it stands after the event.
 */
class SupplierNotifier
{
    /**
     * Announce a freshly placed purchase order to the supplier's feed, with
     * the amount still due on it and the branch-wise dues summary.
     */
    public static function orderPlaced(PurchaseOrder $order): void
    {
        $order->loadMissing('location');

        $supplier = self::supplierOf($order->supplier_id);
        $orderDue = self::orderDue($order);
        $outstanding = self::outstanding($supplier, $order->location_id);

        NotificationService::notifySupplier(
            $supplier,
            "New purchase order {$order->reference_no}",
            sprintf(
                'New purchase order %s from %s — %s.%s Total outstanding to you: %s. %s',
                $order->reference_no,
                $order->location?->name ?? 'Unknown branch',
                Money::format($order->grand_total),
                $orderDue > 0 ? sprintf(' Amount due: %s.', Money::format($orderDue)) : '',
                Money::format($outstanding['total']),
                SupplierDuesBoard::paymentSummary($supplier)
            ),
            route('suppliers.profile', $supplier),
            'info',
            self::branchId($order->location_id)
        );
    }

    /**
     * Announce the amount due on an order moving because it was edited
     * (grand total or paid amount) or approved. A zero change stays silent.
     */
    public static function orderDueChanged(PurchaseOrder $order, float $change, ?User $actor = null): void
    {
        if (round($change, 2) === 0.0) {
            return;
        }

        $order->loadMissing('location');

        $supplier = self::supplierOf($order->supplier_id);
        $outstanding = self::outstanding($supplier, $order->location_id);
        $increased = $change > 0;
        $branch = $order->location?->name ?? 'Unknown branch';

        NotificationService::notifySupplier(
            $supplier,
            sprintf('Amount due %s — %s', $increased ? 'increased' : 'reduced', $order->reference_no),
            sprintf(
                'Your due on purchase order %s from %s %s by %s%s. %s now owes %s. Total outstanding to you: %s. %s',
                $order->reference_no,
                $branch,
                $increased ? 'rose' : 'fell',
                Money::format(abs($change)),
                $actor !== null ? sprintf(' (edited by %s)', $actor->name) : '',
                $branch,
                Money::format($outstanding['branch']),
                Money::format($outstanding['total']),
                SupplierDuesBoard::paymentSummary($supplier)
            ),
            route('suppliers.profile', $supplier),
            $increased ? 'warning' : 'success',
            self::branchId($order->location_id)
        );
    }

    /**
     * Short notice that an order was cancelled, with the due it released.
     */
    public static function orderWithdrawn(PurchaseOrder $order, float $releasedDue): void
    {
        $order->loadMissing('location');

        $supplier = self::supplierOf($order->supplier_id);
        $outstanding = self::outstanding($supplier, $order->location_id);

        NotificationService::notifySupplier(
            $supplier,
            "Purchase order {$order->reference_no} cancelled",
            sprintf(
                'Purchase order %s from %s was cancelled.%s Total outstanding to you: %s.',
                $order->reference_no,
                $order->location?->name ?? 'Unknown branch',
                round($releasedDue, 2) > 0 ? sprintf(' Due reduced by %s.', Money::format($releasedDue)) : '',
                Money::format($outstanding['total'])
            ),
            route('suppliers.profile', $supplier),
            'warning',
            self::branchId($order->location_id)
        );
    }

    /**
     * Announce a purchase payment (Purchase Dues or Finance) to the
     * supplier's feed with amount, date, method, branch, recorder and the
     * branch-wise cleared / still owing summary. A payment that actioned a
     * supplier payment request says so in the same notification.
     */
    public static function purchasePaymentRecorded(PurchasePayment $payment, ?User $actor = null): void
    {
        $payment->loadMissing('purchaseOrder.location', 'purchaseOrder.supplier', 'creator', 'paymentRequest');

        /** @var Supplier $supplier */
        $supplier = $payment->purchaseOrder?->supplier;
        $request = $payment->paymentRequest;

        NotificationService::notifySupplier(
            $supplier,
            sprintf(
                '%s — %s',
                $request !== null ? 'Payment request actioned' : 'Payment received',
                $payment->purchaseOrder?->reference_no ?? ''
            ),
            sprintf(
                '%s%s recorded on %s via %s for %s by %s. %s',
                $request !== null ? sprintf('Your payment request of %s was actioned. ', Money::format($request->amount)) : '',
                Money::format($payment->amount),
                ($payment->payment_date ?? $payment->created_at)->format('d M Y'),
                $payment->methodLabel() ?? 'Unspecified method',
                $payment->purchaseOrder?->location?->name ?? 'Unknown branch',
                $actor?->name ?? $payment->creator?->name ?? 'Unknown user',
                SupplierDuesBoard::paymentSummary($supplier)
            ),
            route('suppliers.profile', $supplier),
            'success',
            $payment->locationId()
        );
    }

    /**
     * Announce a reversed purchase payment: the due it settled is raised again.
     */
    public static function purchasePaymentReversed(PurchasePayment $payment, ?User $actor = null): void
    {
        $payment->loadMissing('purchaseOrder.location');

        $order = $payment->purchaseOrder;
        $supplier = self::supplierOf($order->supplier_id);
        $outstanding = self::outstanding($supplier, $order->location_id);
        $branch = $order->location?->name ?? 'Unknown branch';

        NotificationService::notifySupplier(
            $supplier,
            "Payment reversed — {$order->reference_no}",
            sprintf(
                '%s paid on %s at %s was reversed by %s. The due is raised again: %s now owes %s. Total outstanding to you: %s. %s',
                Money::format($payment->amount),
                $order->reference_no,
                $branch,
                $actor?->name ?? 'Unknown user',
                $branch,
                Money::format($outstanding['branch']),
                Money::format($outstanding['total']),
                SupplierDuesBoard::paymentSummary($supplier)
            ),
            route('suppliers.profile', $supplier),
            'warning',
            self::branchId($order->location_id)
        );
    }

    /**
     * Announce an approved purchase return credit (or its reversal). The
     * credit is tracked as the supplier's return credit balance; the
     * outstanding figures are the branch's and the supplier's purchase dues.
     */
    public static function returnCreditChanged(PurchaseReturn $return, float $change, ?User $actor = null): void
    {
        if (round($change, 2) === 0.0) {
            return;
        }

        $return->loadMissing('location');

        $supplier = self::supplierOf($return->supplier_id);
        $outstanding = self::outstanding($supplier, $return->location_id);
        $branch = $return->location?->name ?? 'Unknown branch';
        $credited = $change > 0;

        NotificationService::notifySupplier(
            $supplier,
            sprintf('Purchase return %s %s', $return->reference_no, $credited ? 'credited' : 'credit reversed'),
            sprintf(
                '%s Return credit still due from you: %s. Outstanding on purchases at %s: %s. Total outstanding to you: %s.',
                $credited
                    ? sprintf('Credit of %s for purchase return %s from %s%s.', Money::format(abs($change)), $return->reference_no, $branch, $actor !== null ? sprintf(' recorded by %s', $actor->name) : '')
                    : sprintf('Credit of %s for purchase return %s from %s was reversed.', Money::format(abs($change)), $return->reference_no, $branch),
                Money::format($supplier->purchase_return_due),
                $branch,
                Money::format($outstanding['branch']),
                Money::format($outstanding['total'])
            ),
            route('suppliers.profile', $supplier),
            $credited ? 'info' : 'warning',
            self::branchId($return->location_id)
        );
    }

    /**
     * Announce a supplier-level (opening / due / return due) payment to the
     * supplier's feed with the same branch-wise dues summary.
     */
    public static function openingPaymentRecorded(SupplierOpeningPayment $payment, ?User $actor = null): void
    {
        $payment->loadMissing('supplier', 'creator');

        NotificationService::notifySupplier(
            $payment->supplier,
            'Payment recorded',
            sprintf(
                '%s recorded on %s via %s by %s. %s',
                Money::format($payment->amount),
                $payment->payment_date?->format('d M Y') ?? $payment->created_at->format('d M Y'),
                $payment->payment_type ?? 'Unspecified method',
                $actor?->name ?? $payment->creator?->name ?? 'Unknown user',
                SupplierDuesBoard::paymentSummary($payment->supplier)
            ),
            route('suppliers.profile', $payment->supplier),
            'success'
        );
    }

    /**
     * A fresh copy of the supplier row, so totals reflect every write made
     * earlier in the same request.
     */
    private static function supplierOf(int|string|null $supplierId): Supplier
    {
        return Supplier::query()->findOrFail($supplierId);
    }

    /**
     * Unpaid remainder of a purchase order.
     */
    private static function orderDue(PurchaseOrder $order): float
    {
        return round(max(0, (float) $order->grand_total - (float) $order->paid_amount), 2);
    }

    /**
     * Amount the given branch still owes the supplier, plus the supplier's
     * total outstanding purchase due (the figure the profile shows).
     *
     * @return array{branch: float, total: float}
     */
    private static function outstanding(Supplier $supplier, int|string|null $locationId): array
    {
        return [
            'branch' => round((float) SupplierDuesBoard::branches($supplier)->where('location_id', (int) $locationId)->sum('due'), 2),
            'total' => round((float) $supplier->purchase_due, 2),
        ];
    }

    private static function branchId(int|string|null $locationId): ?int
    {
        return $locationId !== null ? (int) $locationId : null;
    }
}
