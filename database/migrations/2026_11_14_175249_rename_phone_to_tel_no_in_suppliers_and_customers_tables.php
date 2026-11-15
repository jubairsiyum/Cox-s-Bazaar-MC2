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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('phone', 'tel_no');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('phone', 'tel_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('tel_no', 'phone');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('tel_no', 'phone');
        });
    }
};
