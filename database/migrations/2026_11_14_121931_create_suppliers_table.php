<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_id', 30)->unique();
            $table->string('name');
            $table->string('mobile')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->decimal('purchase_due', 15, 2)->default(0.00);
            $table->decimal('purchase_return_due', 15, 2)->default(0.00);
            $table->string('country')->nullable()->default('Bangladesh');
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
