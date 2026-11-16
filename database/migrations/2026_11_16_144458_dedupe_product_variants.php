<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-off data fix: merge legacy duplicate product variants into a single
     * row so the variant uniqueness rule never trips over historical twins.
     *
     * A variant is identified by case/whitespace-insensitive (name, storage,
     * color) — the same identity used by Product::scopeMatchingVariant().
     * The earliest row survives; its stock absorbs the duplicates' quantities
     * and ledger history so `quantity = SUM(movements)` keeps holding.
     */
    public function up(): void
    {
        $groups = [];

        foreach (DB::table('products')->whereNull('deleted_at')->orderBy('id')->get() as $product) {
            $key = mb_strtolower(trim($product->name))
                .'|'.mb_strtolower(trim((string) ($product->storage ?? '')))
                .'|'.mb_strtolower(trim((string) ($product->color ?? '')));

            $groups[$key][] = $product;
        }

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            $keeper = array_shift($group);

            foreach ($group as $duplicate) {
                $keeperRow = DB::table('units')->where('product_id', $keeper->id)->first();
                $duplicateRow = DB::table('units')->where('product_id', $duplicate->id)->first();

                if ($duplicateRow && ! $keeperRow) {
                    // The keeper has no stock row yet: adopt the duplicate's one.
                    DB::table('units')->where('id', $duplicateRow->id)->update(['product_id' => $keeper->id]);
                } elseif ($duplicateRow) {
                    // Move ledger history and transfer line items onto the
                    // keeper's row, then merge quantities.
                    DB::table('stock_movements')->where('unit_id', $duplicateRow->id)->update(['unit_id' => $keeperRow->id]);
                    DB::table('stock_transfer_items')->where('unit_id', $duplicateRow->id)->update(['unit_id' => $keeperRow->id]);

                    DB::table('units')->where('id', $keeperRow->id)->update([
                        'quantity' => (int) $keeperRow->quantity + (int) $duplicateRow->quantity,
                    ]);

                    DB::table('units')->where('id', $duplicateRow->id)->update(['deleted_at' => now(), 'updated_at' => now()]);
                }

                // Keep prices that only the duplicate carried.
                DB::table('products')->where('id', $keeper->id)->update(array_filter([
                    'purchase_price' => $keeper->purchase_price ?? $duplicate->purchase_price,
                    'sale_price' => $keeper->sale_price ?? $duplicate->sale_price,
                    'updated_at' => now(),
                ], fn ($value) => $value !== null));

                DB::table('products')->where('id', $duplicate->id)->update(['deleted_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Merged duplicates cannot be restored automatically.
     */
    public function down(): void
    {
        //
    }
};
