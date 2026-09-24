<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_detail_id')->constrained('rental_details')->cascadeOnDelete();
            $table->date('report_date')->index();
            $table->decimal('start_hm', 10, 2);
            $table->decimal('end_hm', 10, 2);
            $table->decimal('total_work_hours', 8, 2);
            $table->decimal('standby_hours', 8, 2)->default(0.00);
            $table->decimal('breakdown_hours', 8, 2)->default(0.00);
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
