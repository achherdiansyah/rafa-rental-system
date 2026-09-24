<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_price_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_price_id')->constrained('equipment_prices')->cascadeOnDelete();
            $table->decimal('old_base_rate', 15, 2);
            $table->decimal('new_base_rate', 15, 2);
            $table->timestamp('changed_at')->useCurrent();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_price_versions');
    }
};
