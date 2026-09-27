<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp / channel delivery log (Phase 11E). Audit of every outbound attempt
 * incl. honest SKIPPED states when a provider is not configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60)->index();
            $table->string('channel', 20)->default('whatsapp')->index();
            $table->nullableMorphs('recipient');
            $table->string('recipient_phone', 32)->nullable();
            $table->string('provider', 60)->nullable();
            $table->string('status', 20)->index()->comment('SENT | FAILED | SKIPPED');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
