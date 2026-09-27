<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_prices', function (Blueprint $table) {
            $table->decimal('mob_cost', 15, 2)->default(0)->after('overtime_rate')->comment('Biaya mobilisasi per unit fisik');
            $table->decimal('demob_cost', 15, 2)->default(0)->after('mob_cost')->comment('Biaya demobilisasi per unit fisik');
        });

        Schema::table('booking_details', function (Blueprint $table) {
            $table->decimal('mob_cost_snapshot', 15, 2)->nullable()->after('rental_rate_snapshot')->comment('Snapshot MOB per unit fisik');
            $table->decimal('demob_cost_snapshot', 15, 2)->nullable()->after('mob_cost_snapshot')->comment('Snapshot DEMOB per unit fisik');
        });
    }

    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->dropColumn(['mob_cost_snapshot', 'demob_cost_snapshot']);
        });

        Schema::table('equipment_prices', function (Blueprint $table) {
            $table->dropColumn(['mob_cost', 'demob_cost']);
        });
    }
};
