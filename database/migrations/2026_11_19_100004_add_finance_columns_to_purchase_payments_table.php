<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            // Finance-side linkage: which cash/bank account the money came from.
            $table->foreignId('account_id')->nullable()->after('purchase_order_id')
                ->constrained('accounts')->nullOnDelete();
            $table->date('payment_date')->nullable()->after('amount')->index();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('payment_date');
        });
    }
};
