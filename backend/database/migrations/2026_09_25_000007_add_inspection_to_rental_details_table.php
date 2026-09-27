<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_details', function (Blueprint $table) {
            $table->string('inspection_result', 20)->nullable()->after('status')->comment('READY | MAINTENANCE | DAMAGED');
            $table->timestamp('checked_out_at')->nullable()->after('inspection_result')->comment('Waktu selesai pengembalian/inspeksi');
        });
    }

    public function down(): void
    {
        Schema::table('rental_details', function (Blueprint $table) {
            $table->dropColumn(['inspection_result', 'checked_out_at']);
        });
    }
};
