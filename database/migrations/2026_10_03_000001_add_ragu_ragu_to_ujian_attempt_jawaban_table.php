<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_attempt_jawaban', function (Blueprint $table) {
            $table->boolean('ragu_ragu')->default(false)->after('soal_bank_opsi_id');
        });
    }

    public function down(): void
    {
        Schema::table('ujian_attempt_jawaban', function (Blueprint $table) {
            $table->dropColumn('ragu_ragu');
        });
    }
};
