<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['project_locations', 'equipment_pools'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('province_code', 20)->nullable()->after('city')->index();
                $table->string('province_name', 150)->nullable()->after('province_code');
                $table->string('regency_code', 20)->nullable()->after('province_name')->index();
                $table->string('regency_name', 150)->nullable()->after('regency_code');
                $table->string('district_code', 20)->nullable()->after('regency_name')->index();
                $table->string('district_name', 150)->nullable()->after('district_code');
                $table->string('village_code', 20)->nullable()->after('district_name')->index();
                $table->string('village_name', 150)->nullable()->after('village_code');
            });
        }
    }

    public function down(): void
    {
        foreach (['project_locations', 'equipment_pools'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'province_code', 'province_name', 'regency_code', 'regency_name',
                    'district_code', 'district_name', 'village_code', 'village_name',
                ]);
            });
        }
    }
};
