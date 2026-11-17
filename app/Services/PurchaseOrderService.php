<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;

/**
 * Keeps the stock ledger and the supplier payable in sync with a purchase
 * order's lifecycle.
 *
 * Goods arrive in counted stock once the order is received and leave again
 * if the receipt is ever reversed. The unpaid remainder feeds the supplier's
 * purchase due balance, mirroring the Supplier::purchase_due bookkeeping used
 * by supplier payments.
 */
class PurchaseOrderService
{
    /**
     * Post the stock movements implied by moving an order from one purchase status to another.
     */
    public static function applyStatusChange(PurchaseOrder $order, ?PurchaseStatus $from, PurchaseStatus $to): void
    {
        $receivedBefore = $from?->hasReceived() ?? false;
        $receivedNow = $to->hasReceived();

        if ($receivedBefore === $receivedNow) {
            return;
        }

        $order->load('items');

        foreach ($order->items->groupBy('unit_id') as $items) {
            /** @var Unit $unit */
            $unit = Unit::query()->findOrFail($items->first()->unit_id);
            $quantity = (int) $items->sum('quantity');

            if ($quantity < 1) {
                continue;
            }

            if ($receivedNow) {
                StockService::restock($unit, $quantity, $order, self::note($order, 'received'));
            } else {
                StockService::purchaseReturn($unit, $quantity, $order, self::note($order, 'receipt reversed'));
            }
        }
    }

    /**
     * Move the unpaid remainder of an order between supplier due balances.
     *
     * Cancelled orders no longer contribute to the supplier's purchase due.
     * Pass the original attributes on update/delete, or null when the order
     * is newly created.
     *
     * @param  array{supplier_id?: int|null, grand_total?: float|int|string, paid_amount?: float|int|string, purchase_status?: string}|null  $original
     */
    public static function applySupplierDue(PurchaseOrder $order, ?array $original = null): void
    {
        $oldDue = 0.0;

        if ($original !== null && ($original['purchase_status'] ?? null) !== PurchaseStatus::Cancelled->value) {
            $oldDue = self::contribution($original['grand_total'] ?? 0, $original['paid_amount'] ?? 0);
            $oldSupplier = Supplier::query()->find($original['supplier_id'] ?? null);

            if ($oldSupplier !== null && $oldDue > 0) {
                self::adjustDue($oldSupplier, -$oldDue);
            }
        }

        if ($order->purchase_status !== PurchaseStatus::Cancelled && $order->supplier_id) {
            $newDue = self::contribution($order->grand_total, $order->paid_amount);

            if ($newDue > 0) {
                self::adjustDue($order->supplier, $newDue);
            }
        }
    }

    /**
     * Drop the unpaid remainder of an order from its supplier's due balance.
     * Used when the order is deleted.
     */
    public static function releaseSupplierDue(PurchaseOrder $order): void
    {
        if ($order->purchase_status === PurchaseStatus::Cancelled || ! $order->supplier_id) {
            return;
        }

        $due = self::contribution($order->grand_total, $order->paid_amount);

        if ($due > 0) {
            self::adjustDue($order->supplier, -$due);
        }
    }

    /**
     * Unpaid remainder of an order.
     */
    private static function contribution(float|int|string|null $grandTotal, float|int|string|null $paid): float
    {
        return round(max(0, (float) $grandTotal - (float) $paid), 2);
    }

    /**
     * Apply a signed delta to the supplier's purchase due balance.
     */
    private static function adjustDue(Supplier $supplier, float $delta): void
    {
        $supplier->update([
            'purchase_due' => round(max(0, (float) $supplier->purchase_due + $delta), 2),
        ]);
    }

    /**
     * Human-readable ledger note for a purchase movement.
     */
    private static function note(PurchaseOrder $order, string $event): string
    {
        return "Purchase order {$order->reference_no} {$event}.";
    }
}
