<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_attempt_jawaban', function (Blueprint $table) {
            $table->text('jawaban_teks')->nullable()->after('soal_bank_opsi_id');
            $table->json('jawaban_pasangan')->nullable()->after('jawaban_teks');
        });
    }

    public function down(): void
    {
        Schema::table('ujian_attempt_jawaban', function (Blueprint $table) {
            $table->dropColumn(['jawaban_teks', 'jawaban_pasangan']);
        });
    }
};
