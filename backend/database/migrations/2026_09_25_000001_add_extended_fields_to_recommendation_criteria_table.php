<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendation_criteria', function (Blueprint $table) {
            $table->decimal('work_volume', 12, 2)->nullable()->after('project_type')->comment('Volume pekerjaan yang dibutuhkan');
            $table->decimal('depth_requirement', 8, 2)->nullable()->after('terrain_condition')->comment('Kedalaman galian/pekerjaan dalam meter');
            $table->decimal('reach_requirement', 8, 2)->nullable()->after('depth_requirement')->comment('Jangkauan alat dalam meter');
            $table->string('target_productivity', 100)->nullable()->after('load_capacity')->comment('Target produktivitas per hari/jam');
            $table->string('location_access', 100)->nullable()->after('target_productivity')->comment('Kondisi akses jalan ke lokasi');
            $table->integer('duration_days')->nullable()->after('location_access')->comment('Estimasi durasi proyek dalam hari');
            $table->json('additional_params')->nullable()->after('budget_range')->comment('Konfigurasi/parameter tambahan fleksibel');
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_criteria', function (Blueprint $table) {
            $table->dropColumn([
                'work_volume',
                'depth_requirement',
                'reach_requirement',
                'target_productivity',
                'location_access',
                'duration_days',
                'additional_params',
            ]);
        });
    }
};
