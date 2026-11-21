<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SalesReturnStatus;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Unit;

/**
 * Applies an approved sales return to stock and to the linked invoice.
 *
 * Credit note semantics: an approved return credits the invoice's net value
 * (grand_total − credit_amount). The credit eats the outstanding due first;
 * anything beyond the due reduces the recorded payments, i.e. the excess was
 * already collected and is now owed back to the customer. Restockable lines
 * come back into stock through StockService as sale returns.
 */
class SalesReturnService
{
    /**
     * Apply the return's effects after approval and settle its status:
     * restocked returns become Completed, the rest stay Approved.
     */
    public static function apply(SalesReturn $return): void
    {
        $return->load('items.unit', 'salesInvoice');

        self::creditInvoice($return);

        $restocked = false;

        foreach ($return->items as $item) {
            if (! $item->restock) {
                continue;
            }

            /** @var Unit $unit */
            $unit = $item->unit;

            StockService::saleReturn(
                $unit,
                (int) $item->quantity,
                $return,
                "Sales return {$return->return_no} restocked.",
                $return->location_id !== null ? (int) $return->location_id : null
            );

            $restocked = true;
        }

        $return->update([
            'restocked_at' => $restocked ? now() : null,
            'status' => $restocked ? SalesReturnStatus::Completed : SalesReturnStatus::Approved,
        ]);
    }

    /**
     * Write the return's value back to the linked invoice as a credit note.
     */
    public static function creditInvoice(SalesReturn $return): void
    {
        $invoice = $return->salesInvoice;

        if ($invoice === null) {
            return;
        }

        $credit = round((float) $invoice->credit_amount + (float) $return->refund_amount, 2);
        $netTotal = round((float) $invoice->grand_total - $credit, 2);

        $invoice->update([
            'credit_amount' => $credit,
            // Over-credit beyond the net total reduces recorded payments:
            // that money was collected and is now owed back to the customer.
            'paid_amount' => round(min((float) $invoice->paid_amount, max(0, $netTotal)), 2),
        ]);
    }

    /**
     * Recompute the refund value of a return from its line items.
     * Prices are the discount-adjusted net prices of the linked invoice lines.
     */
    public static function recalculate(SalesReturn $return): void
    {
        $return->update([
            'refund_amount' => round((float) $return->items()->sum('subtotal'), 2),
        ]);
    }

    /**
     * Discount-adjusted net unit price of an invoice line, used to price
     * returned quantities fairly.
     */
    public static function netUnitPrice(SalesInvoice $invoice, int $unitId): float
    {
        $item = $invoice->items->firstWhere('unit_id', $unitId);

        if ($item === null || (int) $item->quantity <= 0) {
            return 0.0;
        }

        return round(((float) $item->quantity * (float) $item->unit_price - (float) $item->discount) / (int) $item->quantity, 2);
    }
}
