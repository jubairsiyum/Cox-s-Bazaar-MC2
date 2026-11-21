<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier-side tracking of the purchase order state machine.
 *
 * purchase_status carries the manager-facing document state (ordered /
 * received / cancelled; "Pending" is a derived display state) and
 * supplier_status tracks the supplier side: pending (placed, awaiting the
 * supplier's acceptance) → accepted → delivered. The timestamps document when
 * each step happened and received_at when stock last posted for the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('supplier_status', 20)->default('pending')->after('payment_status')->index();
            $table->dateTime('accepted_at')->nullable()->after('approved_at');
            $table->dateTime('delivered_at')->nullable()->after('accepted_at');
            $table->dateTime('received_at')->nullable()->after('delivered_at');
        });

        // Existing documents were effective immediately under the old
        // lifecycle, so an ordered purchase reads as already accepted and a
        // received purchase as already delivered — their displays do not move.
        DB::table('purchase_orders')
            ->where('purchase_status', 'ordered')
            ->update(['supplier_status' => 'accepted']);

        DB::table('purchase_orders')
            ->where('purchase_status', 'received')
            ->update([
                'supplier_status' => 'delivered',
                'accepted_at' => DB::raw('updated_at'),
                'delivered_at' => DB::raw('updated_at'),
                'received_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['supplier_status', 'accepted_at', 'delivered_at', 'received_at']);
        });
    }
};
