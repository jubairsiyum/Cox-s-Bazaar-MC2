<?php

declare(strict_types=1);

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
        Schema::create('day_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->date('closing_date');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('cash_sales', 15, 2)->default(0);
            $table->decimal('cash_collections', 15, 2)->default(0);
            $table->decimal('cash_expenses', 15, 2)->default(0);
            $table->decimal('cash_remittances', 15, 2)->default(0);
            $table->decimal('system_cash', 15, 2)->default(0);
            $table->decimal('counted_cash', 15, 2)->default(0);
            $table->decimal('variance', 15, 2)->default(0);
            $table->string('variance_status', 20)->default('exact'); // exact, minor, significant
            $table->string('status', 20)->default('closed'); // closed, reopened
            $table->text('note')->nullable();
            $table->foreignId('closed_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'closing_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_closings');
    }
};
