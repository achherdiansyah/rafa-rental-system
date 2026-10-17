<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendation_requests', function (Blueprint $table) {
            $table->foreignId('project_location_id')->nullable()->after('user_id')
                ->constrained('project_locations')->nullOnDelete();
        });

        Schema::table('recommendation_results', function (Blueprint $table) {
            $table->json('score_breakdown')->nullable()->after('match_score')
                ->comment('Normalized per-criteria raw score, weight and contribution');
            $table->unsignedInteger('available_units')->nullable()->after('score_breakdown')
                ->comment('Available physical units at evaluation period');
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_results', function (Blueprint $table) {
            $table->dropColumn(['score_breakdown', 'available_units']);
        });

        Schema::table('recommendation_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_location_id');
        });
    }
};
