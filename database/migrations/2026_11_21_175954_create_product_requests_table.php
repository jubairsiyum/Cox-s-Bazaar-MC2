<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new product a branch asks the Super Admin to add to the catalog. The
 * submitted details mirror the product form; nothing reaches the catalog
 * until the Super Admin approves it, which then links the created product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('name');
            $table->string('photo')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('model', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('storage', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('unit', 50)->default('Pcs');
            $table->string('product_status', 20)->default('active');
            $table->string('note', 1000)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('created_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requests');
    }
};
