<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // One approved timesheet can only ever own one daily invoice.
            $table->unique('timesheet_id');
        });

        Schema::create('rental_payment_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->string('reason_code', 30);
            $table->foreignId('trigger_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('outstanding_amount', 14, 2)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_reason')->nullable();
            $table->timestamps();

            $table->index(['rental_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_payment_holds');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['timesheet_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
