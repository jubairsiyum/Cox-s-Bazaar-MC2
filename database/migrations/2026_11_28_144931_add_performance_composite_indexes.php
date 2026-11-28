<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes for high-frequency dashboard, notification, and report queries.
     */
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->index(['location_id', 'status', 'invoice_date'], 'sales_invoices_loc_status_date_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
        });

        Schema::table('account_transfers', function (Blueprint $table): void {
            $table->index(['status', 'transfer_date'], 'account_transfers_status_date_idx');
        });

        Schema::table('day_closings', function (Blueprint $table): void {
            $table->index(['location_id', 'status', 'closing_date'], 'day_closings_loc_status_date_idx');
        });
    }

    /**
     * Reverse the composite performance indexes.
     */
    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropIndex('sales_invoices_loc_status_date_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_read_idx');
        });

        Schema::table('account_transfers', function (Blueprint $table): void {
            $table->dropIndex('account_transfers_status_date_idx');
        });

        Schema::table('day_closings', function (Blueprint $table): void {
            $table->dropIndex('day_closings_loc_status_date_idx');
        });
    }
};
