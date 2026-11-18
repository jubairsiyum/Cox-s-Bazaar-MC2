<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerCollection;
use App\Models\CustomerCollectionAllocation;
use App\Models\SalesInvoice;

/**
 * Keeps invoice payment fields in sync with customer collections.
 *
 * A collection allocates money to one or more open invoices; each allocation
 * raises the invoice's paid amount and the invoice's saving hook derives the
 * remaining due and the payment status. Deleting a collection reverses the
 * exact same amounts.
 */
class CustomerCollectionService
{
    /**
     * Apply every allocation of a collection to its invoice.
     */
    public static function applyAllocations(CustomerCollection $collection): void
    {
        foreach ($collection->allocations as $allocation) {
            self::shiftPaid($allocation, 1);
        }
    }

    /**
     * Reverse every allocation of a collection from its invoice.
     */
    public static function releaseAllocations(CustomerCollection $collection): void
    {
        foreach ($collection->allocations as $allocation) {
            self::shiftPaid($allocation, -1);
        }
    }

    /**
     * Move an allocation's amount onto (+1) or off (−1) its invoice.
     */
    private static function shiftPaid(CustomerCollectionAllocation $allocation, int $direction): void
    {
        /** @var SalesInvoice|null $invoice */
        $invoice = SalesInvoice::query()->find($allocation->sales_invoice_id);

        if ($invoice === null) {
            return;
        }

        $invoice->update([
            'paid_amount' => round(max(0, (float) $invoice->paid_amount + $direction * (float) $allocation->amount), 2),
        ]);
    }
}
