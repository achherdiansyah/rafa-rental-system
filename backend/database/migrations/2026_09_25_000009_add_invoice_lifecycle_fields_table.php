<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type', 30)->default('DAILY_WORK')->after('invoice_number')->index()->comment('DAILY_WORK | MOB_DEMOB | ADJUSTMENT | OTHER');
            $table->timestamp('issued_at')->nullable()->after('due_at')->comment('Waktu penerbitan = sumber hitungan deadline');
            $table->timestamp('due_at')->nullable()->change()->comment('Deadline = issued_at + 24 jam');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable(false)->change();
            $table->dropColumn(['issued_at', 'invoice_type']);
        });
    }
};
