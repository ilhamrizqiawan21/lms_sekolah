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
        Schema::table('absensi', function (Blueprint $table) {
            $table->boolean('is_daring')->default(false)->after('status');
            $table->foreignId('kelas_daring_id')->nullable()->after('is_daring')->constrained('kelas_daring')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropForeign(['kelas_daring_id']);
            $table->dropColumn(['is_daring', 'kelas_daring_id']);
        });
    }
};
