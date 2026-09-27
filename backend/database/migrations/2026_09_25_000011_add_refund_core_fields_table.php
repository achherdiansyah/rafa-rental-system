<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund lifecycle fields (Phase 11A).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('source', 30)->nullable()->after('invoice_id')->comment('CANCELLATION | OVERPAYMENT');
            // customer_bank_info diisi saat procesing (manual staff), bukan saat registrasi.
            $table->text('customer_bank_info')->nullable()->change();
            $table->string('transfer_reference', 255)->nullable()->after('customer_bank_info')->comment('Referensi transfer manual');
            $table->string('failure_reason', 500)->nullable()->after('transfer_reference');
            $table->timestamp('completed_at')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->text('customer_bank_info')->nullable(false)->change();
            $table->dropColumn(['source', 'transfer_reference', 'failure_reason', 'completed_at']);
        });
    }
};
