<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->string('start_time', 10)->nullable()->after('report_date')->comment('Jam mulai kerja (HH:mm)');
            $table->string('end_time', 10)->nullable()->after('start_time')->comment('Jam selesai kerja (HH:mm)');
            $table->decimal('start_hm', 10, 2)->nullable()->change();
            $table->decimal('end_hm', 10, 2)->nullable()->change();
        });

        Schema::table('timesheet_revisions', function (Blueprint $table) {
            $table->string('old_start_time', 10)->nullable()->after('old_end_hm');
            $table->string('old_end_time', 10)->nullable()->after('old_start_time');
            $table->decimal('old_start_hm', 10, 2)->nullable()->change();
            $table->decimal('old_end_hm', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timesheet_revisions', function (Blueprint $table) {
            $table->dropColumn(['old_start_time', 'old_end_time']);
        });

        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
