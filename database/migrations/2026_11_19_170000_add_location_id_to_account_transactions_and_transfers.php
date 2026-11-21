<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Strict branch isolation for finance: every account transaction and account
 * transfer is stamped with the branch (location) it happened at so the whole
 * Finance section can be scoped to the acting user's own branch.
 *
 * Stamping rule (kept in sync with the model hooks):
 *  - the account's location when the account belongs to a branch;
 *  - the creator's own branch otherwise.
 * Rows that can resolve neither stay NULL — shared/head-office activity that
 * only the Super Admin sees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_transactions', function (Blueprint $table): void {
            $table->foreignId('location_id')
                ->nullable()
                ->after('account_id')
                ->constrained('locations')
                ->nullOnDelete();
        });

        Schema::table('account_transfers', function (Blueprint $table): void {
            $table->foreignId('location_id')
                ->nullable()
                ->after('to_account_id')
                ->constrained('locations')
                ->nullOnDelete();
        });

        // Backfill existing rows: the account's location, falling back to the
        // creator's branch (their assigned outlet). Correlated subqueries keep
        // this portable across MySQL and SQLite.
        foreach (['account_transactions' => 'account_id', 'account_transfers' => 'from_account_id'] as $table => $accountColumn) {
            DB::table($table)
                ->whereNull('location_id')
                ->update([
                    'location_id' => DB::raw("(select accounts.location_id from accounts where accounts.id = {$table}.{$accountColumn})"),
                ]);

            DB::table($table)
                ->whereNull('location_id')
                ->update([
                    'location_id' => DB::raw("(select users.branch_id from users where users.id = {$table}.created_by)"),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('account_transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
