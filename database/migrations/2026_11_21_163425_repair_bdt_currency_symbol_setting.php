<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'currency_symbol')
            ->where('value', 'à§³')
            ->update(['value' => '৳']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'currency_symbol')
            ->where('value', '৳')
            ->update(['value' => 'à§³']);
    }
};
