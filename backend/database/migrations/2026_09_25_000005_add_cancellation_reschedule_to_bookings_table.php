<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('rejection_reason')->comment('Waktu pembatalan disahkan');
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at')->comment('Alasan pembatalan');
            $table->timestamp('reschedule_requested_at')->nullable()->after('cancellation_reason')->comment('Waktu request reschedule');
            $table->string('reschedule_reason', 500)->nullable()->after('reschedule_requested_at')->comment('Alasan reschedule');
            $table->json('reschedule_history')->nullable()->after('reschedule_reason')->comment('Riwayat perubahan tanggal lama/baru');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason',
                'reschedule_requested_at',
                'reschedule_reason',
                'reschedule_history',
            ]);
        });
    }
};
