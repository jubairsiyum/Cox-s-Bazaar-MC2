<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('type', 20)->index(); // income|expense
            $table->string('category', 50)->index();
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date')->index();
            $table->string('method', 20)->nullable(); // cash|bank|other
            $table->string('reference_no', 50)->nullable()->index();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|completed|cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
