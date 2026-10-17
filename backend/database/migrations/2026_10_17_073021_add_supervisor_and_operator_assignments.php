<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('supervisor_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_validated_at')->nullable()->after('approved_at');
            $table->string('supervisor_decision', 20)->nullable()->after('supervisor_validated_at')
                ->comment('VALIDATED | REVISION');
            $table->text('supervisor_notes')->nullable()->after('supervisor_decision');
        });

        Schema::create('rental_operator_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->foreignId('rental_detail_id')->constrained('rental_details')->cascadeOnDelete();
            $table->foreignId('operator_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('status', 20)->default('ACTIVE')->index(); // ACTIVE | ENDED
            $table->text('end_reason')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rental_detail_id', 'status']);
            $table->index(['operator_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_operator_assignments');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_user_id');
            $table->dropColumn(['supervisor_validated_at', 'supervisor_decision', 'supervisor_notes']);
        });
    }
};