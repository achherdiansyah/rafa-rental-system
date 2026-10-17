<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->foreignId('origin_pool_id')->nullable()->after('equipment_model_id')
                ->constrained('equipment_pools')->nullOnDelete();
            $table->string('origin_name', 150)->nullable()->after('origin_pool_id');
            $table->text('origin_address')->nullable()->after('origin_name');
            $table->decimal('origin_latitude', 10, 8)->nullable()->after('origin_address');
            $table->decimal('origin_longitude', 11, 8)->nullable()->after('origin_latitude');

            $table->foreignId('destination_project_location_id')->nullable()->after('origin_longitude')
                ->constrained('project_locations')->nullOnDelete();
            $table->string('destination_name', 255)->nullable()->after('destination_project_location_id');
            $table->text('destination_address')->nullable()->after('destination_name');
            $table->decimal('destination_latitude', 10, 8)->nullable()->after('destination_address');
            $table->decimal('destination_longitude', 11, 8)->nullable()->after('destination_latitude');

            $table->decimal('route_distance_km', 10, 2)->nullable()->after('destination_longitude')
                ->comment('Jarak rute satu arah (km) dari pool ke lokasi proyek');
            $table->decimal('distance_rate_per_km', 12, 2)->nullable()->after('route_distance_km')
                ->comment('Snapshot tarif per km saat booking dibuat');
        });
    }

    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_pool_id');
            $table->dropConstrainedForeignId('destination_project_location_id');
            $table->dropColumn([
                'origin_name',
                'origin_address',
                'origin_latitude',
                'origin_longitude',
                'destination_name',
                'destination_address',
                'destination_latitude',
                'destination_longitude',
                'route_distance_km',
                'distance_rate_per_km',
            ]);
        });
    }
};