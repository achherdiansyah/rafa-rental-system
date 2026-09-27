<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('sender_name', 255)->nullable()->after('payment_date')->comment('Nama pengirim transfer');
            $table->string('reference', 255)->nullable()->after('sender_name')->comment('Nomor referensi / berita transfer');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['sender_name', 'reference']);
        });
    }
};
