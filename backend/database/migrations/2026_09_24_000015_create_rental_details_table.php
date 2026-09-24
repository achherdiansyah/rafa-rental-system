<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->foreignId('assignment_id')->unique()->constrained('booking_unit_assignments')->restrictOnDelete();
            $table->decimal('check_in_hm', 10, 2)->nullable();
            $table->decimal('check_out_hm', 10, 2)->nullable();
            $table->text('condition_notes')->nullable();
            $table->string('status', 30)->default('PENDING_ASSIGNMENT');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_details');
    }
};
