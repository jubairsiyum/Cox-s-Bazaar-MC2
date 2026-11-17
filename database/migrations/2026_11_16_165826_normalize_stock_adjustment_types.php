<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Map adjustment types from the old Normal/Abnormal scheme onto the
     * Add/Remove/Set operations without changing their meaning:
     *  - normal   = stock set to a counted quantity  -> set
     *  - abnormal = quantity removed from stock      -> remove
     */
    public function up(): void
    {
        DB::table('stock_adjustments')->where('type', 'normal')->update(['type' => 'set']);
        DB::table('stock_adjustments')->where('type', 'abnormal')->update(['type' => 'remove']);

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->string('type', 20)->default('set')->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_adjustments')->where('type', 'set')->update(['type' => 'normal']);
        DB::table('stock_adjustments')->where('type', 'remove')->update(['type' => 'abnormal']);

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->string('type', 20)->default('normal')->change();
        });
    }
};
