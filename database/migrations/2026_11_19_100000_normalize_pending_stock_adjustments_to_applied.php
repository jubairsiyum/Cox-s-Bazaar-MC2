<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The approval step for stock adjustments has been retired: adjustments
     * apply to stock the moment they are saved. Legacy rows still marked
     * pending predate that change and are normalized to applied so the lists
     * and reports render them as completed instead of carrying dead workflow
     * state.
     */
    public function up(): void
    {
        DB::table('stock_adjustments')
            ->where('approval_status', 'pending')
            ->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        // The original per-row approval state is not recoverable: rows
        // normalized here now read as applied, which matches the retired
        // workflow's treatment of them.
    }
};
