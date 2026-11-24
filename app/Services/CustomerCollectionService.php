<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Account;
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

        if ($collection->location_id !== null && (float) $collection->amount > 0) {
            $branchTill = Account::where('location_id', $collection->location_id)
                ->where('type', AccountType::Cash)
                ->first();
            if ($branchTill) {
                $branchTill->increment('current_balance', (float) $collection->amount);
            }
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

        if ($collection->location_id !== null && (float) $collection->amount > 0) {
            $branchTill = Account::where('location_id', $collection->location_id)
                ->where('type', AccountType::Cash)
                ->first();
            if ($branchTill) {
                $branchTill->decrement('current_balance', min((float) $branchTill->current_balance, (float) $collection->amount));
            }
        }
    }

    /**
     * Move an allocation's amount onto (+1) or off (−1) its invoice.
     */
    private static function shiftPaid(CustomerCollectionAllocation $allocation, int $direction): void
    {
        /** @var SalesInvoice|null $invoice */
        $invoice = SalesInvoice::query()->whereKey($allocation->sales_invoice_id)->lockForUpdate()->first();

        if ($invoice === null) {
            return;
        }

        $currentPaid = MoneyMath::clean((string) $invoice->paid_amount);
        $amount = MoneyMath::clean((string) $allocation->amount);

        if ($direction > 0) {
            $newPaid = MoneyMath::add($currentPaid, $amount);
        } else {
            $newPaid = MoneyMath::max('0.00', MoneyMath::sub($currentPaid, $amount));
        }

        $invoice->update([
            'paid_amount' => $newPaid,
        ]);
    }
}
