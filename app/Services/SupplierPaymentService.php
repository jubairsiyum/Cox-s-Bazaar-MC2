<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchasePayment;
use App\Models\User;

/**
 * Applies and reverses the financial effects of a supplier payment
 * (App\Models\PurchasePayment), mirroring the semantics used by the Purchase
 * Dues approval flow: the purchase invoice's paid amount and the supplier's
 * purchase due move together, and — for finance-recorded payments — the money
 * leaves the chosen cash/bank account.
 *
 * Status effects (the purchase order state machine and the supplier's feed)
 * are delegated to {@see PurchaseOrderWorkflow}: a payment never changes the
 * branch side or the supplier side, and every applied payment is announced on
 * the supplier's profile.
 */
class SupplierPaymentService
{
    public function __construct(private AccountBalanceService $balances) {}

    /**
     * Apply a payment: settle part of the purchase invoice, reduce the
     * supplier's purchase due and debit the paying account (if any).
     */
    public function apply(PurchasePayment $payment): void
    {
        $amount = round((float) $payment->amount, 2);
        $order = $payment->purchaseOrder;

        if ($order !== null) {
            $order->paid_amount = round((float) $order->paid_amount + $amount, 2);
            $order->save();

            if ($order->supplier_id) {
                $supplier = $order->supplier;
                $supplier->update([
                    'purchase_due' => round(max(0, (float) $supplier->purchase_due - $amount), 2),
                ]);
            }
        }

        if ($payment->account_id) {
            $this->balances->debit($payment->account_id, $amount);
        }

        PurchaseOrderWorkflow::paymentApplied($payment);
    }

    /**
     * Reverse a previously applied payment: restore the purchase invoice due,
     * the supplier's purchase due and the paying account balance.
     *
     * A reversal moves neither side of the order: a received order stays
     * Received and Delivered, whatever its payment state. The re-raised due
     * is announced on the supplier's profile.
     */
    public function reverse(PurchasePayment $payment, ?User $actor = null): void
    {
        $amount = round((float) $payment->amount, 2);
        $order = $payment->purchaseOrder;

        if ($order !== null) {
            $order->paid_amount = round(max(0, (float) $order->paid_amount - $amount), 2);
            $order->save();

            if ($order->supplier_id) {
                $supplier = $order->supplier;
                $supplier->update([
                    'purchase_due' => round((float) $supplier->purchase_due + $amount, 2),
                ]);
            }
        }

        if ($payment->account_id) {
            $this->balances->credit($payment->account_id, $amount);
        }

        PurchaseOrderWorkflow::paymentReversed($payment, $actor);
    }
}
