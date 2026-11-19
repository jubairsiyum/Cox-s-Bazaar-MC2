<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // Payment capture on the "Confirm Payment & Generate Invoice" step.
            // The amount received always equals the order total (full payment
            // is required), so it is recorded on the generated invoice's
            // paid_amount / due_amount columns instead of being duplicated here.
            $table->string('payment_method', 20)->nullable()->after('grand_total');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'paid_at']);
        });
    }
};
