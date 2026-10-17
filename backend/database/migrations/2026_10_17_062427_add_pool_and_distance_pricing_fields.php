<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default origin pool per model (booking is model-level before unit assignment).
        Schema::table('equipment_models', function (Blueprint $table) {
            $table->foreignId('equipment_pool_id')
                ->nullable()
                ->after('equipment_type_id')
                ->constrained('equipment_pools')
                ->nullOnDelete();
        });

        // Actual origin pool per physical unit (precise identification).
        Schema::table('equipment_units', function (Blueprint $table) {
            $table->foreignId('equipment_pool_id')
                ->nullable()
                ->after('equipment_model_id')
                ->constrained('equipment_pools')
                ->nullOnDelete();
        });

        // Per-km distance tariff managed via Master Tarif & Harga.
        Schema::table('equipment_prices', function (Blueprint $table) {
            $table->decimal('distance_rate_per_km', 12, 2)->default(0.00)->after('overtime_rate');
        });

        // Tariff change history for the per-km rate.
        Schema::table('equipment_price_versions', function (Blueprint $table) {
            $table->decimal('old_distance_rate_per_km', 12, 2)->nullable()->after('new_base_rate');
            $table->decimal('new_distance_rate_per_km', 12, 2)->nullable()->after('old_distance_rate_per_km');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_price_versions', function (Blueprint $table) {
            $table->dropColumn(['old_distance_rate_per_km', 'new_distance_rate_per_km']);
        });

        Schema::table('equipment_prices', function (Blueprint $table) {
            $table->dropColumn('distance_rate_per_km');
        });

        Schema::table('equipment_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_pool_id');
        });

        Schema::table('equipment_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_pool_id');
        });
    }
};