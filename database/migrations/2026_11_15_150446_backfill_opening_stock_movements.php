<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill opening stock movements for quantity entries that predate the
     * stock ledger, so that every unit's quantity equals the sum of its
     * movements. No-op on fresh installations (tables are empty when this runs).
     */
    public function up(): void
    {
        $now = now();

        DB::table('units')
            ->whereNotIn('id', DB::table('stock_movements')->select('unit_id'))
            ->orderBy('id')
            ->chunkById(100, function ($units) use ($now) {
                foreach ($units as $unit) {
                    if ((int) $unit->quantity <= 0) {
                        continue;
                    }

                    DB::table('stock_movements')->insert([
                        'unit_id' => $unit->id,
                        'type' => 'opening',
                        'quantity' => (int) $unit->quantity,
                        'quantity_after' => (int) $unit->quantity,
                        'note' => 'Opening stock',
                        'created_by' => $unit->created_by,
                        'created_at' => $unit->created_at ?? $now,
                        'updated_at' => $unit->updated_at ?? $now,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * Data backfill: removing the ledger entries would leave stock without a
     * supporting movement history, so this migration is intentionally not
     * reversible. Use StockService::adjust() to correct quantities instead.
     */
    public function down(): void
    {
        //
    }
};
