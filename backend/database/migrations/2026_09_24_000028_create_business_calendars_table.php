<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_calendars', function (Blueprint $table) {
            $table->id();
            $table->date('calendar_date')->unique();
            $table->boolean('is_working_day')->default(true);
            $table->string('holiday_name', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_calendars');
    }
};
