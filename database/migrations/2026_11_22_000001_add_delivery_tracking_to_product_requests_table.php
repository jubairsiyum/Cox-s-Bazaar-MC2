<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery tracking for approved product requests: after the Super Admin
 * approves a request the product is "in transit" to the requesting branch,
 * and becomes "delivered" once that branch receives it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->string('delivery_status', 20)->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn(['delivery_status', 'delivered_at']);
        });
    }
};
