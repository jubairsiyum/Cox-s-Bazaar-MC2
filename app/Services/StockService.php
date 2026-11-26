<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
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
 *
 * Every movement is stamped with the location it happened at (strict branch
 * isolation): flows pass the document's location explicitly, and when they
 * don't the movement lands on the acting user's own branch. A unit's stock
 * AT a location is derived from these stamps — see quantityAt().
 */
class StockService
{
    /**
     * Product sold: stock goes out.
     */
    public static function sell(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null, ?int $locationId = null): StockMovement
    {
        return self::apply($unit, -self::positiveAmount($quantity), StockMovementType::Sale, $reference, $note, $locationId);
    }

    /**
     * Sold product returned by the customer: stock comes back in.
     */
    public static function saleReturn(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null, ?int $locationId = null): StockMovement
    {
        return self::apply($unit, self::positiveAmount($quantity), StockMovementType::SaleReturn, $reference, $note, $locationId);
    }

    /**
     * New stock arrived (e.g. bulk delivery from a supplier): stock goes up.
     */
    public static function restock(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null, ?int $locationId = null): StockMovement
    {
        return self::apply($unit, self::positiveAmount($quantity), StockMovementType::Restock, $reference, $note, $locationId);
    }

    /**
     * Stock returned to the supplier: stock goes out.
     */
    public static function purchaseReturn(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null, ?int $locationId = null): StockMovement
    {
        return self::apply($unit, -self::positiveAmount($quantity), StockMovementType::PurchaseReturn, $reference, $note, $locationId);
    }

    /**
     * Stock dispatched from the source location of a transfer: stock goes out.
     */
    public static function transferOut(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null, ?int $locationId = null): StockMovement
    {
        return self::apply($unit, -self::positiveAmount($quantity), StockMovementType::TransferOut, $reference, $note, $locationId);
    }

    /**
     * Stock arrived at the destination location of a transfer: stock comes back in.
     */
    public static function transferIn(Unit $unit, int $quantity, ?Model $reference = null, ?string $note = null, ?int $locationId = null): StockMovement
    {
        return self::apply($unit, self::positiveAmount($quantity), StockMovementType::TransferIn, $reference, $note, $locationId);
    }

    /**
     * Move stock between two explicit locations in one atomic step, preserving
     * the global invariant "units.quantity equals the sum of its movements".
     * The global row total is unchanged; only the location attribution of the
     * movements changes (a -qty at the source, a +qty at the destination).
     *
     * Either location may be null, which is the central pool (movements with no
     * location stamp). This is what the per-actor `transferOut`/`transferIn`
     * pair cannot express: a branch user moving stock out of the central pool.
     *
     * @throws InsufficientStockException when the source holds less than the requested quantity.
     */
    public static function transferBetweenLocations(Unit $unit, int $quantity, ?int $fromLocationId, ?int $toLocationId, ?Model $reference = null, ?string $note = null): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Stock quantity must be at least 1.');
        }

        DB::transaction(function () use ($unit, $quantity, $fromLocationId, $toLocationId, $reference, $note): void {
            $locked = self::lock($unit);
            $available = self::availableAt($locked, $fromLocationId);

            if ($available < $quantity) {
                $fromName = $fromLocationId === null ? null : Location::query()->whereKey($fromLocationId)->value('name');

                throw InsufficientStockException::for($locked->displayName(), $available, $quantity, $fromName);
            }

            $global = (int) $locked->quantity;
            $creatorId = Auth::id() ?? $locked->created_by;

            $locked->stockMovements()->create([
                'location_id' => $fromLocationId,
                'type' => StockMovementType::TransferOut,
                'quantity' => -$quantity,
                'quantity_after' => $global,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'created_by' => $creatorId,
            ]);

            $locked->stockMovements()->create([
                'location_id' => $toLocationId,
                'type' => StockMovementType::TransferIn,
                'quantity' => $quantity,
                'quantity_after' => $global,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'created_by' => $creatorId,
            ]);

            AuditService::log(
                'updated',
                $locked,
                ['quantity' => $global],
                ['quantity' => $global],
                "Transferred {$quantity} {$locked->unit} of '{$locked->displayName()}' to a branch."
            );
        });
    }

    /**
     * Set the stock to an absolute quantity (stock count / correction).
     * Returns null when the quantity is already correct.
     */
    public static function adjust(Unit $unit, int $quantity, ?string $note = null, ?int $locationId = null): ?StockMovement
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Adjusted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($unit, $quantity, $note, $locationId) {
            $locked = self::lock($unit);
            $current = (int) $locked->quantity;
            $delta = $quantity - $current;

            if ($delta === 0) {
                return null;
            }

            self::assertNotBelowZero($locked, $delta, $locationId);

            $locked->updateQuietly(['quantity' => $quantity]);

            return self::record($locked, StockMovementType::Adjustment, $delta, $current, $quantity, null, $note ?? 'Stock adjusted', $locationId);
        });
    }

    /**
     * Branch stock count / correction: set the stock of a unit AT ONE LOCATION
     * to an absolute quantity (derived from the ledger, see quantityAt()).
     * The global stock row moves by the same delta so the invariant
     * "units.quantity equals the sum of its movements" holds.
     */
    public static function countAt(Unit $unit, int $quantity, int $locationId, ?string $note = null): ?StockMovement
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Adjusted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($unit, $quantity, $locationId, $note) {
            $locked = self::lock($unit);
            $current = self::quantityAt($locked, $locationId);
            $delta = $quantity - $current;

            if ($delta === 0) {
                return null;
            }

            self::assertNotBelowZero($locked, $delta, $locationId);

            $global = (int) $locked->quantity + $delta;

            $locked->updateQuietly(['quantity' => $global]);

            return self::record($locked, StockMovementType::Adjustment, $delta, (int) $locked->quantity, $global, null, $note ?? 'Stock counted', $locationId);
        });
    }

    // ── Canonical current-stock derivation (per location) ────────────

    /**
     * Current stock of one unit at one location, derived from the movement
     * ledger: the sum of every signed movement recorded at that location.
     * This is THE canonical per-location current-stock query — reuse it (or
     * its batch siblings below) instead of hand-rolling sums.
     */
    public static function quantityAt(Unit|int $unit, int $locationId): int
    {
        return (int) StockMovement::query()
            ->where('unit_id', $unit instanceof Unit ? $unit->id : $unit)
            ->where('location_id', $locationId)
            ->sum('quantity');
    }

    /**
     * Stock of one unit available at one location, where null is the central
     * pool (movements with no location stamp). This is the figure stock
     * transfers and every stock decrease are checked against.
     */
    public static function availableAt(Unit|int $unit, ?int $locationId): int
    {
        return (int) StockMovement::query()
            ->where('unit_id', $unit instanceof Unit ? $unit->id : $unit)
            ->when(
                $locationId === null,
                fn ($query) => $query->whereNull('location_id'),
                fn ($query) => $query->where('location_id', $locationId)
            )
            ->sum('quantity');
    }

    /**
     * Quantities usable for selling at a location. A warehouse also holds the
     * unassigned central pool, where products created by a Super Admin start.
     *
     * @return array<int, int> unit_id => saleable quantity
     */
    public static function saleableQuantitiesAt(int $locationId): array
    {
        $query = StockMovement::query();
        self::scopeToLocationWithCentral($query, $locationId);

        return $query
            ->groupBy('unit_id')
            ->selectRaw('unit_id, COALESCE(SUM(quantity), 0) as total')
            ->pluck('total', 'unit_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Limit a movement query to a location, including the unassigned central
     * pool when that location is a warehouse.
     */
    private static function scopeToLocationWithCentral(Builder $query, int $locationId): void
    {
        $query->where(function (Builder $inner) use ($locationId): void {
            $inner->where('location_id', $locationId);

            if (self::isWarehouse($locationId)) {
                $inner->orWhereNull('location_id');
            }
        });
    }

    private static function isWarehouse(?int $locationId): bool
    {
        if ($locationId === null) {
            return false;
        }

        return Location::query()->toBase()->where('id', $locationId)->value('type') === LocationType::Warehouse->value;
    }

    /**
     * Batch variant of quantityAt(): the stock of every unit with ledger
     * activity at one location, in a single query (no N+1). Units without
     * any movement at the location are absent from the result.
     *
     * @return array<int, int> unit_id => quantity at the location
     */
    public static function quantitiesByUnitAt(int $locationId): array
    {
        return StockMovement::query()
            ->where('location_id', $locationId)
            ->groupBy('unit_id')
            ->selectRaw('unit_id, COALESCE(SUM(quantity), 0) as total')
            ->pluck('total', 'unit_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Branch-wise availability of the given stock rows in one aggregate
     * query: every (unit, location) pair with stock, with its location name.
     *
     * @param  iterable<int, Unit|int>  $units
     * @return Collection<int, array{unit_id: int, location_id: int, location_name: string, quantity: int}>
     */
    public static function availabilityAcross(iterable $units): SupportCollection
    {
        $unitIds = collect($units)->map(fn ($unit) => $unit instanceof Unit ? $unit->id : (int) $unit)->all();

        if ($unitIds === []) {
            return collect();
        }

        return StockMovement::query()
            ->join('locations', 'locations.id', '=', 'stock_movements.location_id')
            ->whereIn('stock_movements.unit_id', $unitIds)
            ->whereNotNull('stock_movements.location_id')
            ->whereNull('locations.deleted_at')
            ->groupBy('stock_movements.unit_id', 'stock_movements.location_id', 'locations.name')
            ->selectRaw('stock_movements.unit_id as unit_id, stock_movements.location_id as location_id, locations.name as location_name, COALESCE(SUM(stock_movements.quantity), 0) as quantity')
            ->havingRaw('COALESCE(SUM(stock_movements.quantity), 0) > 0')
            ->orderBy('locations.name')
            ->get()
            ->map(fn ($row): array => [
                'unit_id' => (int) $row->unit_id,
                'location_id' => (int) $row->location_id,
                'location_name' => (string) $row->location_name,
                'quantity' => (int) $row->quantity,
            ]);
    }

    /**
     * The quantity to DISPLAY for stock rows within a user's scope: the
     * shared row total for unrestricted users (Super Admin), the row's
     * quantity at the user's own branch for everyone else. Batch-loads a
     * whole result set at once (no N+1).
     *
     * @param  iterable<int, Unit>  $units
     * @return array<int, int> unit_id => scoped quantity
     */
    public static function scopedQuantitiesFor(iterable $units, User $user): array
    {
        if (BranchScope::isUnrestricted($user)) {
            return collect($units)
                ->mapWithKeys(fn (Unit $unit): array => [$unit->id => (int) $unit->quantity])
                ->all();
        }

        $branchId = BranchScope::locationId($user);
        $atBranch = $branchId === null ? [] : self::quantitiesByUnitAt($branchId);

        return collect($units)
            ->mapWithKeys(fn (Unit $unit): array => [$unit->id => $atBranch[$unit->id] ?? 0])
            ->all();
    }

    /**
     * Apply a signed quantity delta atomically.
     */
    private static function apply(Unit $unit, int $delta, StockMovementType $type, ?Model $reference, ?string $note, ?int $locationId = null): StockMovement
    {
        return DB::transaction(function () use ($unit, $delta, $type, $reference, $note, $locationId) {
            $locked = self::lock($unit);
            $current = (int) $locked->quantity;

            self::assertNotBelowZero($locked, $delta, $locationId);

            $new = $current + $delta;

            $locked->updateQuietly(['quantity' => $new]);

            return self::record($locked, $type, $delta, $current, $new, $reference, $note, $locationId);
        });
    }

    /**
     * Guard: a stock change must never leave a unit below zero, neither in its
     * overall stock nor at the location it lands on. Increases are never
     * blocked. Callers must hold the unit's row lock (see lock()), so the
     * figures checked here cannot change before the write. Negative stock is
     * only allowed when the "allow_negative_stock" setting is on.
     *
     * @throws InsufficientStockException
     */
    public static function assertNotBelowZero(Unit $unit, int $delta, ?int $locationId = null): void
    {
        if ($delta >= 0 || self::allowsNegativeStock()) {
            return;
        }

        $location = $locationId ?? self::actorLocationId();
        $availableHere = self::availableAt($unit, $location);

        // A warehouse also holds the unassigned central pool, where products
        // added by a Super Admin start their life. Sales from the main
        // warehouse must be able to consume that stock.
        if (self::isWarehouse($location)) {
            $availableHere += self::availableAt($unit, null);
        }

        $availableOverall = (int) $unit->quantity;

        if ($availableHere + $delta >= 0 && $availableOverall + $delta >= 0) {
            return;
        }

        $locationName = $location === null ? null : Location::query()->whereKey($location)->value('name');
        $shortfallHere = $availableHere + $delta < 0;

        throw InsufficientStockException::for(
            $unit->displayName(),
            $shortfallHere ? $availableHere : $availableOverall,
            abs($delta),
            $shortfallHere ? $locationName : null
        );
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
     *
     * The movement is stamped with the location the change happened at:
     * the flow's explicit location when given, otherwise the acting user's
     * own branch (strict branch isolation).
     */
    private static function record(Unit $unit, StockMovementType $type, int $delta, int $old, int $new, ?Model $reference, ?string $note, ?int $locationId = null): StockMovement
    {
        $movement = $unit->stockMovements()->create([
            'location_id' => $locationId ?? self::actorLocationId(),
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
    public static function allowsNegativeStock(): bool
    {
        return (bool) Setting::get('allow_negative_stock', '0');
    }

    /**
     * The location movements land on when a flow does not stamp one: the
     * acting user's own branch (Super Admin and unassigned users post to the
     * shared central pool, i.e. no location stamp).
     */
    public static function actorLocationId(): ?int
    {
        return Auth::user()?->branch_id !== null ? (int) Auth::user()->branch_id : null;
    }
}
