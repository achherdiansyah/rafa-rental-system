<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('recommendation_requests')->cascadeOnDelete();
            $table->foreignId('equipment_model_id')->constrained('equipment_models')->cascadeOnDelete();
            $table->decimal('match_score', 5, 2);
            $table->text('reasoning_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_results');
    }
};
