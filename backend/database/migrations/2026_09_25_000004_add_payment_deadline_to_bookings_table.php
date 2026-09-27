<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status')->comment('Waktu booking disetujui Admin');
            $table->timestamp('payment_deadline_at')->nullable()->after('approved_at')->comment('Tenggat bayar (approved_at + grace)');
            $table->timestamp('payment_met_at')->nullable()->after('payment_deadline_at')->comment('Waktu pembayaran memenuhi syarat (diisi Phase 10)');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'payment_deadline_at', 'payment_met_at']);
        });
    }
};
