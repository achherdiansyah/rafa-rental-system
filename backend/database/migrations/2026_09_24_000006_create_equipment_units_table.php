<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_model_id')->constrained('equipment_models')->restrictOnDelete();
            $table->string('serial_number', 100)->unique();
            $table->string('plate_number', 30)->nullable()->unique();
            $table->string('status', 30)->default('AVAILABLE')->index();
            $table->decimal('last_hour_meter', 10, 2)->default(0.00);
            $table->unsignedSmallInteger('year_of_make')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_units');
    }
};
