<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->unsignedInteger('break_minutes')->default(0)->after('end_hm')->comment('Durasi istirahat dalam menit');
            $table->string('operator_name', 255)->nullable()->after('break_minutes')->comment('Nama operator lapangan');
            $table->text('notes')->nullable()->after('operator_name')->comment('Catatan kondisi/hari kerja');
            $table->string('signature_reference', 255)->nullable()->after('notes')->comment('Referensi tanda tangan PIC (file/upload id)');
            $table->unique(['rental_detail_id', 'report_date'], 'timesheets_rental_detail_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropUnique('timesheets_rental_detail_date_unique');
            $table->dropColumn(['break_minutes', 'operator_name', 'notes', 'signature_reference']);
        });
    }
};
