<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Unit;

/**
 * Keeps the stock ledger and the supplier return due in sync with a purchase
 * return's lifecycle.
 *
 * Returning goods to a supplier removes them from counted stock; reversing a
 * return brings them back. The unpaid remainder feeds the supplier's purchase
 * return due balance (what the supplier still owes back).
 */
class PurchaseReturnService
{
    /**
     * Bring the stock ledger in line with the current line items.
     *
     * Pass the quantities keyed by unit id as they were before the change
     * (empty array on create, the stored items on delete) and the difference
     * is posted exactly once per product.
     *
     * @param  array<int, int>  $originalQuantities
     */
    public static function syncStock(PurchaseReturn $return, array $originalQuantities): void
    {
        $return->load('items');

        $newQuantities = $return->items
            ->groupBy('unit_id')
            ->map(fn ($items) => (int) $items->sum('quantity'))
            ->all();

        $unitIds = array_unique(array_merge(array_keys($originalQuantities), array_keys($newQuantities)));

        foreach ($unitIds as $unitId) {
            $original = (int) ($originalQuantities[$unitId] ?? 0);
            $current = (int) ($newQuantities[$unitId] ?? 0);
            $delta = $current - $original;

            if ($delta === 0) {
                continue;
            }

            /** @var Unit $unit */
            $unit = Unit::query()->findOrFail($unitId);

            if ($delta > 0) {
                StockService::purchaseReturn($unit, $delta, $return, self::note($return, 'returned to supplier'), $return->location_id !== null ? (int) $return->location_id : null);
            } else {
                StockService::restock($unit, -$delta, $return, self::note($return, 'return reversed'), $return->location_id !== null ? (int) $return->location_id : null);
            }
        }
    }

    /**
     * Move the unpaid remainder of a return between supplier return due balances.
     *
     * Only approved returns contribute; a return takes effect financially
     * when the responsible manager approves it. Pass the original attributes
     * on update/delete, or null when the return is newly created.
     *
     * @param  array{supplier_id?: int|null, grand_total?: float|int|string, paid_amount?: float|int|string, approval_status?: string}|null  $original
     * @return float Signed change of the return credit (new minus old).
     */
    public static function applySupplierDue(PurchaseReturn $return, ?array $original = null): float
    {
        $oldDue = 0.0;
        $newDue = 0.0;

        if ($original !== null && self::originalStatusIs($original, ApprovalStatus::Approved)) {
            $oldDue = self::contribution($original['grand_total'] ?? 0, $original['paid_amount'] ?? 0);
            $oldSupplier = Supplier::query()->find($original['supplier_id'] ?? null);

            if ($oldSupplier !== null && $oldDue > 0) {
                self::adjustDue($oldSupplier, -$oldDue);
            }
        }

        if ($return->approval_status === ApprovalStatus::Approved && $return->supplier_id) {
            $newDue = self::contribution($return->grand_total, $return->paid_amount);

            if ($newDue > 0) {
                self::adjustDue($return->supplier, $newDue);
            }
        }

        return round($newDue - $oldDue, 2);
    }

    /**
     * Drop the unpaid remainder of a return from its supplier's return due
     * balance. Used when the return is deleted.
     *
     * @return float Signed change of the return credit (zero or negative).
     */
    public static function releaseSupplierDue(PurchaseReturn $return): float
    {
        if ($return->approval_status !== ApprovalStatus::Approved || ! $return->supplier_id) {
            return 0.0;
        }

        $due = self::contribution($return->grand_total, $return->paid_amount);

        if ($due > 0) {
            self::adjustDue($return->supplier, -$due);
        }

        return round(-$due, 2);
    }

    /**
     * Whether the original attributes record an approved return.
     * getOriginal() hands back casted enum instances, raw arrays hand back strings.
     *
     * @param  array<string, mixed>  $original
     */
    private static function originalStatusIs(array $original, ApprovalStatus $status): bool
    {
        $value = $original['approval_status'] ?? null;

        return $value instanceof ApprovalStatus
            ? $value === $status
            : $value === $status->value;
    }

    /**
     * Unpaid remainder of a return.
     */
    private static function contribution(float|int|string|null $grandTotal, float|int|string|null $paid): float
    {
        return round(max(0, (float) $grandTotal - (float) $paid), 2);
    }

    /**
     * Apply a signed delta to the supplier's purchase return due balance.
     */
    private static function adjustDue(Supplier $supplier, float $delta): void
    {
        $supplier->update([
            'purchase_return_due' => round(max(0, (float) $supplier->purchase_return_due + $delta), 2),
        ]);
    }

    /**
     * Human-readable ledger note for a return movement.
     */
    private static function note(PurchaseReturn $return, string $event): string
    {
        return "Purchase return {$return->reference_no} {$event}.";
    }
}
