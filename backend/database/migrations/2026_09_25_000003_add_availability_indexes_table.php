<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index to accelerate physical-unit availability scans
        Schema::table('equipment_units', function (Blueprint $table) {
            $table->index(['equipment_model_id', 'status'], 'equipment_units_model_status_index');
        });

        // Composite index to accelerate period overlap lookups on booking details
        Schema::table('booking_details', function (Blueprint $table) {
            $table->index(['equipment_model_id', 'start_date', 'end_date'], 'booking_details_model_period_index');
        });

        // Composite index to accelerate current assignment conflict checks
        Schema::table('booking_unit_assignments', function (Blueprint $table) {
            $table->index(['equipment_unit_id', 'is_current'], 'booking_unit_assignments_unit_current_index');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_units', function (Blueprint $table) {
            $table->dropIndex('equipment_units_model_status_index');
        });

        Schema::table('booking_details', function (Blueprint $table) {
            $table->dropIndex('booking_details_model_period_index');
        });

        Schema::table('booking_unit_assignments', function (Blueprint $table) {
            $table->dropIndex('booking_unit_assignments_unit_current_index');
        });
    }
};
