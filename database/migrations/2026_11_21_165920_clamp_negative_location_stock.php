<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clamp negative stock at branch (location) level to zero.
     *
     * Earlier flows let a branch sell, adjust or transfer out stock it had
     * never received, so some location ledgers summed below zero while the
     * overall stock stayed positive. For each such location the shortfall is
     * moved out of the central pool (movements with no location), so the
     * overall stock is unchanged. Only when the central pool cannot cover the
     * shortfall is the rest added as a corrective adjustment, which raises the
     * overall stock; those lines are noted and should be checked by a count.
     */
    public function up(): void
    {
        $negativeLocations = DB::table('stock_movements')
            ->select('unit_id', 'location_id', DB::raw('SUM(quantity) AS total'))
            ->whereNotNull('location_id')
            ->groupBy('unit_id', 'location_id')
            ->havingRaw('SUM(quantity) < 0')
            ->orderBy('unit_id')
            ->orderBy('location_id')
            ->get();

        foreach ($negativeLocations as $row) {
            $unitId = (int) $row->unit_id;
            $locationId = (int) $row->location_id;
            $shortfall = -(int) $row->total;

            $overall = (int) DB::table('units')->where('id', $unitId)->value('quantity');
            $central = (int) DB::table('stock_movements')
                ->where('unit_id', $unitId)
                ->whereNull('location_id')
                ->sum('quantity');

            $fromCentral = min($shortfall, max($central, 0));
            $addedOverall = $shortfall - $fromCentral;

            if ($fromCentral > 0) {
                $this->record($unitId, $locationId, $fromCentral, $overall, "Negative stock at this location clamped to 0: {$fromCentral} unit(s) moved in from central stock.");
                $this->record($unitId, null, -$fromCentral, $overall, "{$fromCentral} unit(s) moved out of central stock to clear negative stock at location #{$locationId}.");
            }

            if ($addedOverall > 0) {
                DB::table('units')->where('id', $unitId)->increment('quantity', $addedOverall);

                $this->record($unitId, $locationId, $addedOverall, $overall + $addedOverall, "Negative stock at this location clamped to 0: {$addedOverall} unit(s) added because central stock could not cover it. Verify with a physical count.");
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Data correction: the clamped movements are part of the stock history and
     * are not removed, so this migration is intentionally not reversible.
     */
    public function down(): void
    {
        //
    }

    /**
     * Write one corrective adjustment movement directly, bypassing StockService
     * (the guard would refuse the very shortfall this migration corrects).
     */
    private function record(int $unitId, ?int $locationId, int $quantity, int $quantityAfter, string $note): void
    {
        DB::table('stock_movements')->insert([
            'unit_id' => $unitId,
            'location_id' => $locationId,
            'type' => 'adjustment',
            'quantity' => $quantity,
            'quantity_after' => $quantityAfter,
            'note' => $note,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
