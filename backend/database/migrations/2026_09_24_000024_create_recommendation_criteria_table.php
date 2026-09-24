<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('recommendation_requests')->cascadeOnDelete();
            $table->string('project_type', 100);
            $table->string('terrain_condition', 100);
            $table->decimal('load_capacity', 10, 2)->nullable();
            $table->string('budget_range', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_criteria');
    }
};
