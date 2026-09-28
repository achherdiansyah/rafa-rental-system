<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes matching the actual hot query paths (reporting filters, status
 * listings, deadline cron, availability assignment). Business behaviour is
 * unchanged — this is pure access-path tuning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->index(['status', 'report_date'], 'timesheets_status_report_date_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'invoices_status_created_idx');
            $table->index('due_at', 'invoices_due_at_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'payment_date'], 'payments_status_payment_date_idx');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'refunds_status_created_idx');
            $table->index('source', 'refunds_source_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'bookings_status_created_idx');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'rentals_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex('rentals_status_created_idx');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_status_created_idx');
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex('refunds_source_idx');
            $table->dropIndex('refunds_status_created_idx');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_payment_date_idx');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_due_at_idx');
            $table->dropIndex('invoices_status_created_idx');
        });
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropIndex('timesheets_status_report_date_idx');
        });
    }
};
