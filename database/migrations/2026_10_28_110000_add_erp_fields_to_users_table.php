<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add ERP-specific columns to the users table.
     * This migration runs AFTER the default users migration.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('status', 20)->default('active')->after('phone');  // active|inactive
            $table->foreignId('branch_id')->nullable()->after('status')
                  ->constrained('branches')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['phone', 'status']);
        });
    }
};
