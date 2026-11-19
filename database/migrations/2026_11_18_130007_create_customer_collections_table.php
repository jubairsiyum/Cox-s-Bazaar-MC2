<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_collections', function (Blueprint $table) {
            $table->id();
            $table->string('collection_no', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->dateTime('collection_date')->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('method', 20)->default('cash');
            $table->string('reference_no', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_collections');
    }
};
