<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a sales invoice's totals, stock effects and linked order in sync
 * with its draft → posted → voided lifecycle.
 *
 * Posting issues the stock-out for every line through StockService; voiding
 * brings the stock back and settles the invoice. Draft invoices are pure
 * documents and never touch stock.
 */
class SalesInvoiceService
{
    /**
     * Recompute the header totals from the stored line items.
     *
     * @param  float|null  $taxRate  Tax rate to apply (keeps the current one when null).
     */
    public static function recalculate(SalesInvoice $invoice, ?float $taxRate = null): void
    {
        $invoice->load('items');

        $discount = round((float) $invoice->items->sum('discount'), 2);
        $subtotal = round((float) $invoice->items->sum('subtotal'), 2);
        $rate = $taxRate ?? (float) $invoice->tax_rate;
        $taxAmount = round($subtotal * $rate / 100, 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_rate' => $rate,
            'tax_amount' => $taxAmount,
            'grand_total' => round($subtotal + $taxAmount, 2),
        ]);
    }

    /**
     * Post a draft invoice: stock goes out and the invoice becomes final.
     *
     * @throws InsufficientStockException
     */
    public static function post(SalesInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->load('items.unit');

            foreach ($invoice->items as $item) {
                /** @var Unit $unit */
                $unit = $item->unit;

                StockService::sell(
                    $unit,
                    (int) $item->quantity,
                    $invoice,
                    "Sales invoice {$invoice->invoice_no} posted."
                );
            }

            $invoice->update([
                'status' => SalesInvoiceStatus::Posted,
                'posted_at' => now(),
            ]);

            self::syncLinkedOrder($invoice);
        });
    }

    /**
     * Void a posted invoice: the stock-out is reversed and nothing is owed.
     */
    public static function void(SalesInvoice $invoice, ?string $reason = null): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $invoice->load('items.unit');

            foreach ($invoice->items as $item) {
                /** @var Unit $unit */
                $unit = $item->unit;

                StockService::restock(
                    $unit,
                    (int) $item->quantity,
                    $invoice,
                    "Sales invoice {$invoice->invoice_no} voided — stock restored."
                );
            }

            $invoice->update([
                'status' => SalesInvoiceStatus::Voided,
                'voided_at' => now(),
                'void_reason' => $reason,
                'due_amount' => 0,
            ]);

            self::syncLinkedOrder($invoice);
        });
    }

    /**
     * Move the parent sales order along with the invoice lifecycle: it reads
     * "Invoiced" while an open invoice exists for it and falls back to
     * "Confirmed" after a void or delete. Call after the invoice row changes.
     */
    public static function syncLinkedOrder(SalesInvoice $invoice): void
    {
        if ($invoice->sales_order_id === null) {
            return;
        }

        $order = SalesOrder::query()->find($invoice->sales_order_id);

        if ($order === null || $order->status === SalesOrderStatus::Cancelled) {
            return;
        }

        $hasOpenInvoice = SalesInvoice::query()
            ->where('sales_order_id', $order->id)
            ->whereIn('status', [SalesInvoiceStatus::Draft, SalesInvoiceStatus::Posted])
            ->exists();

        $target = $hasOpenInvoice ? SalesOrderStatus::Invoiced : SalesOrderStatus::Confirmed;

        if ($order->status !== $target && ($hasOpenInvoice || $order->status === SalesOrderStatus::Invoiced)) {
            $order->update(['status' => $target]);
        }
    }
}
