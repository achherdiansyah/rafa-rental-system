<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_model_id')->constrained('equipment_models')->cascadeOnDelete();
            $table->string('price_type', 20)->default('HOURLY');
            $table->boolean('is_all_in')->default(false)->index();
            $table->decimal('base_rate', 15, 2);
            $table->unsignedInteger('minimum_hours')->default(0);
            $table->decimal('overtime_rate', 15, 2)->default(0.00);
            $table->date('effective_date')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_prices');
    }
};
