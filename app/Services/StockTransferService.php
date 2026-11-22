<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockTransferStatus;
use App\Models\Location;
use App\Models\StockTransfer;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the stock ledger in sync with a stock transfer's lifecycle.
 *
 * Goods leave the source location once the transfer is dispatched
 * (in-transit or received) and arrive back into counted stock once it is
 * received. Movements are only posted when those flags flip between two
 * statuses, so re-saving a transfer never double-counts stock.
 */
class StockTransferService
{
    /**
     * Post the stock movements implied by moving a transfer from one status to another.
     */
    public static function applyStatusChange(StockTransfer $transfer, ?StockTransferStatus $from, StockTransferStatus $to): void
    {
        $leftBefore = $from?->hasLeftSource() ?? false;
        $arrivedBefore = $from?->hasArrived() ?? false;
        $leftNow = $to->hasLeftSource();
        $arrivedNow = $to->hasArrived();

        if ($leftBefore === $leftNow && $arrivedBefore === $arrivedNow) {
            return;
        }

        $transfer->load('items');

        foreach ($transfer->items->groupBy('unit_id') as $items) {
            /** @var Unit $unit */
            $unit = Unit::query()->findOrFail($items->first()->unit_id);
            $quantity = (int) $items->sum('quantity');

            if ($quantity < 1) {
                continue;
            }

            if ($leftNow && ! $leftBefore) {
                StockService::transferOut($unit, $quantity, $transfer, self::note($transfer, 'dispatched'), $transfer->from_location_id !== null ? (int) $transfer->from_location_id : null);
            }

            if ($arrivedNow && ! $arrivedBefore) {
                StockService::transferIn($unit, $quantity, $transfer, self::note($transfer, 'received'), $transfer->to_location_id !== null ? (int) $transfer->to_location_id : null);
            }

            if ($leftBefore && ! $leftNow) {
                StockService::transferIn($unit, $quantity, $transfer, self::note($transfer, 'returned'), $transfer->from_location_id !== null ? (int) $transfer->from_location_id : null);
            }

            if ($arrivedBefore && ! $arrivedNow) {
                StockService::transferOut($unit, $quantity, $transfer, self::note($transfer, 'receipt reversed'), $transfer->to_location_id !== null ? (int) $transfer->to_location_id : null);
            }
        }
    }

    /**
     * Guard: every line must be covered by the stock available at the source
     * location. Runs under the units' row locks, so two transfers cannot both
     * take the same stock. Errors are keyed to the offending line.
     *
     * @param  array<int, array{unit_id: int|string, quantity: int|string}>  $items
     *
     * @throws ValidationException
     */
    public static function assertAvailableAtSource(int $fromLocationId, array $items): void
    {
        $unitIds = collect($items)->map(fn (array $item): int => (int) $item['unit_id'])->unique()->sort()->values()->all();

        Unit::query()->whereIn('id', $unitIds)->orderBy('id')->lockForUpdate()->get();

        $locationName = Location::query()->whereKey($fromLocationId)->value('name') ?? 'the source location';
        $errors = [];

        foreach ($items as $index => $item) {
            $available = StockService::availableAt((int) $item['unit_id'], $fromLocationId);

            if ((int) $item['quantity'] > $available) {
                $errors["items.{$index}.quantity"] = self::availabilityMessage($available, $locationName);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The line error shown when a requested quantity exceeds the stock at the source.
     */
    public static function availabilityMessage(int $available, string $locationName): string
    {
        return $available > 0
            ? "Only {$available} available at {$locationName}."
            : "No stock available at {$locationName}.";
    }

    /**
     * Human-readable ledger note for a transfer movement.
     */
    private static function note(StockTransfer $transfer, string $event): string
    {
        return "Stock transfer {$transfer->reference_no} {$event}.";
    }
}
