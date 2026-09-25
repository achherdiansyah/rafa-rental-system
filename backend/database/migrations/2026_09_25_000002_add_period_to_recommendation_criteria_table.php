<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendation_criteria', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('duration_days')->comment('Tanggal mulai periode sewa yang diinginkan');
            $table->date('end_date')->nullable()->after('start_date')->comment('Tanggal selesai periode sewa yang diinginkan');
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_criteria', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
