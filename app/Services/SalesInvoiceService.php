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
 *
 * Sales orders settle the moment they are paid: {@see generateFromOrder()}
 * copies the order's line items into an invoice that is generated PAID IN
 * FULL (due = 0, payment status "Paid") and posted on the spot. The business
 * never sells on credit, so an order only produces its invoice once the
 * payment has been confirmed — and that single post is the only stock
 * effect the sale ever has.
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
        $cartDiscount = round((float) $invoice->cart_discount, 2);
        $rate = $taxRate ?? (float) $invoice->tax_rate;
        $taxableBase = round(max(0, $subtotal - $cartDiscount), 2);
        $taxAmount = round($taxableBase * $rate / 100, 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_amount' => round($discount + $cartDiscount, 2),
            'cart_discount' => $cartDiscount,
            'tax_rate' => $rate,
            'tax_amount' => $taxAmount,
            'grand_total' => round($taxableBase + $taxAmount, 2),
        ]);
    }

    /**
     * Generate the invoice for a paid-in-full sales order and post it on the
     * spot: the invoice is copied from the order's line items, settled in
     * full (due = 0, payment status "Paid" — never "Payment Due") and the
     * stock-out takes effect immediately, like a POS quick sale. Call inside
     * the order's transaction so an {@see InsufficientStockException} rolls
     * the whole sale back.
     *
     * @throws InsufficientStockException
     */
    public static function generateFromOrder(SalesOrder $order, ?int $createdBy = null): SalesInvoice
    {
        $order->load('items');

        $invoice = SalesInvoice::create([
            'invoice_date' => now(),
            'customer_id' => $order->customer_id,
            'sales_order_id' => $order->id,
            'location_id' => $order->location_id,
            'branch_id' => $order->branch_id,
            'status' => SalesInvoiceStatus::Draft,
            'tax_rate' => (float) $order->tax_rate,
            'note' => 'Generated from Sales Order '.$order->order_no,
            'created_by' => $createdBy,
        ]);

        foreach ($order->items as $item) {
            $invoice->items()->create([
                'unit_id' => (int) $item->unit_id,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'subtotal' => (float) $item->subtotal,
            ]);
        }

        self::recalculate($invoice, (float) $order->tax_rate);

        // Full payment is captured before the invoice is generated: settle it
        // in full so it never reads "Payment Due".
        $invoice->update(['paid_amount' => (float) $invoice->grand_total]);

        self::post($invoice);

        return $invoice;
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
                    "Sales invoice {$invoice->invoice_no} posted.",
                    $invoice->location_id !== null ? (int) $invoice->location_id : null
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
     * Void an invoice: a posted stock-out is reversed and nothing is owed.
     * Draft invoices never touched stock, so voiding one restores nothing.
     */
    public static function void(SalesInvoice $invoice, ?string $reason = null): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $invoice->load('items.unit');

            if ($invoice->status === SalesInvoiceStatus::Posted) {
                foreach ($invoice->items as $item) {
                    /** @var Unit $unit */
                    $unit = $item->unit;

                    StockService::restock(
                        $unit,
                        (int) $item->quantity,
                        $invoice,
                        "Sales invoice {$invoice->invoice_no} voided — stock restored.",
                        $invoice->location_id !== null ? (int) $invoice->location_id : null
                    );
                }
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
