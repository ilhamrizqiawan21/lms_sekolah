<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soal_bank', function (Blueprint $table) {
            $table->string('tipe_soal', 30)->default('pilihan_ganda')->after('pertanyaan');
            $table->string('level_kognitif', 2)->nullable()->after('tipe_soal');
        });
    }

    public function down(): void
    {
        Schema::table('soal_bank', function (Blueprint $table) {
            $table->dropColumn(['tipe_soal', 'level_kognitif']);
        });
    }
};
