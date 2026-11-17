<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single entry point for every stock change on a Unit quantity.
 *
 * All mutations run inside a database transaction with a row lock, record a
 * StockMovement ledger entry (signed delta + resulting quantity) and write an
 * audit log line. Future modules (Sales, Purchases, Returns) must go through
 * these methods instead of updating `units.quantity` directly.
 */
class StockService
{
    /**
     * Product sold: stock goes out.
     */
    public static function sell(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null): StockMovement
    {
        return self::apply($unit, -self::positiveAmount($quantity), StockMovementType::Sale, $reference, $note);
    }

    /**
     * Sold product returned by the customer: stock comes back in.
     */
    public static function saleReturn(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null): StockMovement
    {
        return self::apply($unit, self::positiveAmount($quantity), StockMovementType::SaleReturn, $reference, $note);
    }

    /**
     * New stock arrived (e.g. bulk delivery from a supplier): stock goes up.
     */
    public static function restock(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null): StockMovement
    {
        return self::apply($unit, self::positiveAmount($quantity), StockMovementType::Restock, $reference, $note);
    }

    /**
     * Stock returned to the supplier: stock goes out.
     */
    public static function purchaseReturn(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null): StockMovement
    {
        return self::apply($unit, -self::positiveAmount($quantity), StockMovementType::PurchaseReturn, $reference, $note);
    }

    /**
     * Stock dispatched from the source location of a transfer: stock goes out.
     */
    public static function transferOut(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null): StockMovement
    {
        return self::apply($unit, -self::positiveAmount($quantity), StockMovementType::TransferOut, $reference, $note);
    }

    /**
     * Stock arrived at the destination location of a transfer: stock comes back in.
     */
    public static function transferIn(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null): StockMovement
    {
        return self::apply($unit, self::positiveAmount($quantity), StockMovementType::TransferIn, $reference, $note);
    }

    /**
     * Set the stock to an absolute quantity (stock count / correction).
     * Returns null when the quantity is already correct.
     */
    public static function adjust(Unit $unit, int $quantity, ?string $note = null): ?StockMovement
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Adjusted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($unit, $quantity, $note) {
            $locked = self::lock($unit);
            $current = (int) $locked->quantity;
            $delta = $quantity - $current;

            if ($delta === 0) {
                return null;
            }

            $locked->updateQuietly(['quantity' => $quantity]);

            return self::record($locked, StockMovementType::Adjustment, $delta, $current, $quantity, null, $note ?? 'Stock adjusted');
        });
    }

    /**
     * Apply a signed quantity delta atomically.
     */
    private static function apply(Unit $unit, int $delta, StockMovementType $type, ?Model $reference, ?string $note): StockMovement
    {
        return DB::transaction(function () use ($unit, $delta, $type, $reference, $note) {
            $locked = self::lock($unit);
            $current = (int) $locked->quantity;
            $new = $current + $delta;

            if ($new < 0 && ! self::allowsNegativeStock()) {
                throw InsufficientStockException::for($locked->displayName(), $current, abs($delta));
            }

            $locked->updateQuietly(['quantity' => $new]);

            return self::record($locked, $type, $delta, $current, $new, $reference, $note);
        });
    }

    /**
     * Lock the stock row so concurrent sales cannot oversell.
     */
    private static function lock(Unit $unit): Unit
    {
        return Unit::query()->lockForUpdate()->findOrFail($unit->id);
    }

    /**
     * Write the ledger entry and the audit log line for a stock change.
     */
    private static function record(Unit $unit, StockMovementType $type, int $delta, int $old, int $new, ?Model $reference, ?string $note): StockMovement
    {
        $movement = $unit->stockMovements()->create([
            'type' => $type,
            'quantity' => $delta,
            'quantity_after' => $new,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'created_by' => Auth::id() ?? $unit->created_by,
        ]);

        $action = match ($type) {
            StockMovementType::Sale => 'Sold',
            StockMovementType::SaleReturn => 'Sale returned',
            StockMovementType::Restock => 'Restocked',
            StockMovementType::PurchaseReturn => 'Returned to supplier',
            StockMovementType::Adjustment => 'Adjusted',
            StockMovementType::Opening => 'Opened',
            StockMovementType::TransferOut => 'Transferred out',
            StockMovementType::TransferIn => 'Transferred in',
        };

        AuditService::log(
            'updated',
            $unit,
            ['quantity' => $old],
            ['quantity' => $new],
            "{$action} ".abs($delta)." {$unit->unit} of '{$unit->displayName()}' ({$new} remaining)."
        );

        return $movement;
    }

    /**
     * Guard: every sell / restock amount must be a positive whole number.
     */
    private static function positiveAmount(int $quantity): int
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Stock quantity must be at least 1.');
        }

        return $quantity;
    }

    /**
     * Whether negative stock is permitted by the shop settings.
     */
    private static function allowsNegativeStock(): bool
    {
        return (bool) Setting::get('allow_negative_stock', '0');
    }
}
