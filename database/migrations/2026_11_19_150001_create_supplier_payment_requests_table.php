<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment requests a supplier sends to a branch through their profile
 * (staff act on the supplier's behalf). The targeted branch is notified
 * in-app and can clear the request from Finance > Supplier Payments by
 * recording the payment it links to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->text('message');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('purchase_payments')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_requests');
    }
};
