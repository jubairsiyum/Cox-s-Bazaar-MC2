<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Strict branch isolation makes unassigned users see nothing operational, so
 * the seeded demo accounts must belong to a branch to stay usable.
 *
 * Demo-account assignment choice: all four demo accounts (Demo Manager, Demo
 * Accountant, Demo Sales Staff, Demo Storekeeper) join the flagship
 * 'Brand Shop - Cox's Bazar' outlet (code BS01) — one outlet team, so every
 * demo login lands on a branch with stock, sales and finance activity. When
 * that outlet is absent the first active location is used instead.
 */
return new class extends Migration
{
    /**
     * Location code of the demo branch, with its display name as fallback key.
     */
    private const DEMO_BRANCH_CODE = 'BS01';

    private const DEMO_BRANCH_NAME = 'Brand Shop - Cox\'s Bazar';

    /**
     * Seeded demo account emails.
     *
     * @var array<int, string>
     */
    private const DEMO_EMAILS = [
        'manager@cberp.local',
        'accountant@cberp.local',
        'sales@cberp.local',
        'storekeeper@cberp.local',
    ];

    public function up(): void
    {
        $branchId = DB::table('locations')
            ->whereNull('deleted_at')
            ->where('code', self::DEMO_BRANCH_CODE)
            ->value('id')
            ?? DB::table('locations')
                ->whereNull('deleted_at')
                ->where('name', self::DEMO_BRANCH_NAME)
                ->value('id')
            ?? DB::table('locations')
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id');

        if ($branchId === null) {
            return;
        }

        DB::table('users')
            ->whereIn('email', self::DEMO_EMAILS)
            ->whereNull('branch_id')
            ->update(['branch_id' => $branchId]);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('email', self::DEMO_EMAILS)
            ->update(['branch_id' => null]);
    }
};
