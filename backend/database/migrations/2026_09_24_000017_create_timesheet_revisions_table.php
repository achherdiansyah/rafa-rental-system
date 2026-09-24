<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')->constrained('timesheets')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->decimal('old_start_hm', 10, 2);
            $table->decimal('old_end_hm', 10, 2);
            $table->text('revision_reason');
            $table->foreignId('revised_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_revisions');
    }
};
