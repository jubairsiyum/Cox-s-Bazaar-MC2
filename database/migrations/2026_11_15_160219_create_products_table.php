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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 30)->unique();
            $table->string('name');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('model', 100)->nullable();
            $table->string('barcode', 100)->nullable()->index();
            $table->string('storage', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('note')->nullable();
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
        Schema::dropIfExists('products');
    }
};
