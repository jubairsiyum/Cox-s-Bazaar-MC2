<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Account;
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

        $discount = '0.00';
        $subtotal = '0.00';

        foreach ($invoice->items as $item) {
            $discount = MoneyMath::add($discount, (string) ($item->discount ?? '0.00'));
            $subtotal = MoneyMath::add($subtotal, (string) ($item->subtotal ?? '0.00'));
        }

        $cartDiscount = MoneyMath::clean((string) ($invoice->cart_discount ?? '0.00'));
        $rate = $taxRate !== null ? (string) $taxRate : MoneyMath::clean((string) ($invoice->tax_rate ?? '0.00'));
        $taxableBase = MoneyMath::max('0.00', MoneyMath::sub($subtotal, $cartDiscount));
        $taxAmount = MoneyMath::percent($taxableBase, $rate);
        $grandTotal = MoneyMath::add($taxableBase, $taxAmount);
        $totalDiscount = MoneyMath::add($discount, $cartDiscount);

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_amount' => $totalDiscount,
            'cart_discount' => $cartDiscount,
            'tax_rate' => $rate,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal,
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
            // Concurrency & idempotency: lock invoice and prevent double posting
            $locked = SalesInvoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === SalesInvoiceStatus::Posted) {
                return;
            }

            if ($locked->location_id !== null && $locked->invoice_date !== null) {
                DayClosingService::assertDayNotClosed((int) $locked->location_id, $locked->invoice_date);
            }

            $locked->load('items.unit');

            // Sort units by ID to ensure deadlock-free consistent locking
            $sortedItems = $locked->items->sortBy('unit_id');

            foreach ($sortedItems as $item) {
                /** @var Unit $unit */
                $unit = $item->unit;

                StockService::sell(
                    $unit,
                    (int) $item->quantity,
                    $locked,
                    "Sales invoice {$locked->invoice_no} posted.",
                    $locked->location_id !== null ? (int) $locked->location_id : null
                );
            }

            $locked->update([
                'status' => SalesInvoiceStatus::Posted,
                'posted_at' => now(),
            ]);

            // Credit the branch cash till when cash is paid for the sale
            if ($locked->location_id !== null && (float) $locked->paid_amount > 0) {
                $branchTill = Account::firstOrCreate(
                    [
                        'location_id' => $locked->location_id,
                        'type' => AccountType::Cash,
                    ],
                    [
                        'name' => 'Cash Till - '.($locked->location?->name ?? 'Branch '.$locked->location_id),
                        'opening_balance' => 0,
                        'current_balance' => 0,
                        'status' => 'active',
                    ]
                );
                $branchTill->increment('current_balance', (float) $locked->paid_amount);
            }

            // Credit the branch cash till when cash is paid for the sale
            if ($locked->location_id !== null && (float) $locked->paid_amount > 0) {
                $branchTill = Account::firstOrCreate(
                    [
                        'location_id' => $locked->location_id,
                        'type' => AccountType::Cash,
                    ],
                    [
                        'name' => 'Cash Till - '.($locked->location?->name ?? 'Branch '.$locked->location_id),
                        'opening_balance' => 0,
                        'current_balance' => 0,
                        'status' => 'active',
                    ]
                );
                $branchTill->increment('current_balance', (float) $locked->paid_amount);
            }

            self::syncLinkedOrder($locked);
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

            // Reverse cash from branch cash till if previously collected
            if ($invoice->location_id !== null && (float) $invoice->paid_amount > 0) {
                $branchTill = Account::where('location_id', $invoice->location_id)
                    ->where('type', AccountType::Cash)
                    ->first();
                if ($branchTill) {
                    $branchTill->decrement('current_balance', min((float) $branchTill->current_balance, (float) $invoice->paid_amount));
                }
            }

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
