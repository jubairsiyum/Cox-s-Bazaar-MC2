<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL refuses to add foreign keys while legacy zero dates ("0000-00-00")
        // sit in the table, so normalise them first.
        DB::statement("UPDATE users SET email_verified_at = NULL WHERE CAST(email_verified_at AS CHAR) LIKE '0000%'");

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'location_id')) {
                $table->dropColumn('location_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('branch_id')
                ->constrained('locations')->nullOnDelete();
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->after('payment_status')->index();
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->after('payment_status')->index();
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approved_at']);
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approved_at']);
        });
    }
};
