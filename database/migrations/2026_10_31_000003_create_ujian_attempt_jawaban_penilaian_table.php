<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_attempt_jawaban_penilaian', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ujian_attempt_jawaban_id')->constrained('ujian_attempt_jawaban')->cascadeOnDelete();
            $table->foreignId('dinilai_oleh')->constrained('users')->restrictOnDelete();
            $table->decimal('poin_sebelumnya', 5, 2)->nullable();
            $table->decimal('poin_diberikan', 5, 2);
            $table->text('catatan')->nullable();
            $table->dateTime('created_at');
            $table->index(['ujian_attempt_jawaban_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_attempt_jawaban_penilaian');
    }
};
