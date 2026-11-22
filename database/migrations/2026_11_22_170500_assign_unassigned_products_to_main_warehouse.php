<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill products created before branch assignment existed: every product
 * with a NULL location_id is assigned to the Main Warehouse, and its stock
 * that still sits in the central pool (location_id NULL) is moved to the Main
 * Warehouse so the product's assigned branch matches where its stock lives.
 *
 * Only positive central balances are moved; a unit whose central pool is empty
 * or already negative is left as-is so no location is pushed below zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mainWarehouseId = DB::table('locations')
            ->where('type', 'warehouse')
            ->orderBy('id')
            ->value('id');

        if ($mainWarehouseId === null) {
            return;
        }

        // Capture the unassigned products' stock rows before the backfill
        // wipes their NULL location_id.
        $unitIds = DB::table('units')
            ->join('products', 'products.id', '=', 'units.product_id')
            ->whereNull('products.location_id')
            ->pluck('units.id');

        DB::table('products')->whereNull('location_id')->update(['location_id' => $mainWarehouseId]);

        foreach ($unitIds as $unitId) {
            $central = (int) DB::table('stock_movements')
                ->where('unit_id', $unitId)
                ->whereNull('location_id')
                ->sum('quantity');

            if ($central <= 0) {
                continue;
            }

            $global = (int) DB::table('units')->where('id', $unitId)->value('quantity');
            $now = now();

            // Net-zero relocation: out of the central pool, into the warehouse.
            DB::table('stock_movements')->insert([
                [
                    'unit_id' => $unitId,
                    'location_id' => null,
                    'type' => 'transfer_out',
                    'quantity' => -$central,
                    'quantity_after' => $global,
                    'note' => 'Assigned to Main Warehouse',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'unit_id' => $unitId,
                    'location_id' => $mainWarehouseId,
                    'type' => 'transfer_in',
                    'quantity' => $central,
                    'quantity_after' => $global,
                    'note' => 'Assigned to Main Warehouse',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // No reversible data to restore; the assignment is intended to be permanent.
    }
};
