<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_unit_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_detail_id')->constrained('booking_details')->cascadeOnDelete();
            $table->foreignId('equipment_unit_id')->constrained('equipment_units')->restrictOnDelete();
            $table->string('status', 20)->default('ASSIGNED');
            $table->boolean('is_current')->default(true);
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->text('replaced_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_unit_assignments');
    }
};
