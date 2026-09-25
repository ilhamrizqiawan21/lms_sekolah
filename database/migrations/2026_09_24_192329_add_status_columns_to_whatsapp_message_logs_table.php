<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('total_hari_terlambat');
            $table->string('wamid')->nullable()->after('sent_marked_at');
            $table->string('error_code', 50)->nullable()->after('wamid');
            $table->text('error_message')->nullable()->after('error_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            $table->dropColumn(['status', 'wamid', 'error_code', 'error_message']);
        });
    }
};
