<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 50)->unique();
            $table->dateTime('adjustment_date')->index();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('type', 20)->default('normal')->index();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_amount_recovered', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
