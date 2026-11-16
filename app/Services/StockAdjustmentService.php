<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockAdjustmentType;
use App\Models\StockAdjustment;
use App\Models\Unit;

/**
 * Posts and reverses stock adjustments against the stock ledger.
 *
 * Semantics of a line's quantity depend on the adjustment type:
 *  - Normal   (stock take / correction): the quantity is the COUNTED stock
 *             level; stock is set to it (delta is recorded as an Adjustment).
 *  - Abnormal (damage / loss / theft):   the quantity is the amount REMOVED
 *             from stock.
 *
 * Every line stores the stock level before and after the document, so an
 * adjustment can be reversed exactly as long as nothing has moved since.
 */
class StockAdjustmentService
{
    /**
     * The stock level an adjustment line leaves behind.
     */
    public static function targetQuantity(StockAdjustmentType $type, int $quantity, int $quantityBefore): int
    {
        return match ($type) {
            StockAdjustmentType::Normal => $quantity,
            StockAdjustmentType::Abnormal => max($quantityBefore - $quantity, 0),
        };
    }

    /**
     * Post the stock effect of every line (idempotent: stock is set to the
     * line's stored quantity_after).
     */
    public static function apply(StockAdjustment $adjustment): void
    {
        $adjustment->load('items');

        foreach ($adjustment->items as $item) {
            StockService::adjust($item->unit, $item->quantity_after, self::note($adjustment));
        }
    }

    /**
     * Undo the stock effect of every line by restoring the recorded
     * quantity_before. Only safe while no stock has moved since posting.
     */
    public static function reverse(StockAdjustment $adjustment): void
    {
        $adjustment->load('items');

        foreach ($adjustment->items as $item) {
            StockService::adjust($item->unit, $item->quantity_before, self::note($adjustment));
        }
    }

    /**
     * Replace the line items of an adjustment, capturing the stock level
     * before and after each line.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function saveItems(StockAdjustment $adjustment, array $rows): void
    {
        $adjustment->items()->delete();

        foreach ($rows as $row) {
            $quantity = (int) $row['quantity'];
            $unitPrice = round((float) $row['unit_price'], 2);
            $quantityBefore = (int) Unit::query()->findOrFail((int) $row['unit_id'])->quantity;

            $adjustment->items()->create([
                'unit_id' => (int) $row['unit_id'],
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => self::targetQuantity($adjustment->type, $quantity, $quantityBefore),
                'unit_price' => $unitPrice,
                'subtotal' => round($quantity * $unitPrice, 2),
            ]);
        }
    }

    /**
     * Human-readable ledger note for an adjustment movement.
     */
    private static function note(StockAdjustment $adjustment): string
    {
        return "Stock adjustment {$adjustment->reference_no}.";
    }
}
