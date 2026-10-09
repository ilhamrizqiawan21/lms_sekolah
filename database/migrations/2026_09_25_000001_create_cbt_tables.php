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
        Schema::create('soal_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mapel_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            $table->text('pertanyaan');
            $table->string('topik', 100)->nullable();
            $table->enum('kesulitan', ['mudah', 'sedang', 'sulit'])->default('sedang');
            $table->enum('kategori_nilai', ['NH', 'STS', 'SAS', 'SAT'])->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index(['guru_id', 'mapel_id']);
        });

        Schema::create('soal_bank_opsi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soal_bank_id')->constrained('soal_bank')->cascadeOnDelete();
            $table->text('teks_opsi');
            $table->boolean('is_benar')->default(false);
            $table->unsignedTinyInteger('urutan')->default(0);
        });

        Schema::create('ujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->string('judul', 200);
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('durasi_menit');
            $table->enum('kategori_nilai', ['NH', 'STS', 'SAS', 'SAT'])->default('NH');
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->boolean('acak_soal')->default(true);
            $table->boolean('acak_opsi')->default(true);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('ujian_soal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
            $table->foreignId('soal_bank_id')->constrained('soal_bank')->cascadeOnDelete();
            $table->decimal('poin', 5, 2)->default(1);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->unique(['ujian_id', 'soal_bank_id']);
        });

        Schema::create('ujian_attempt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('status', ['belum_mulai', 'sedang_mengerjakan', 'selesai', 'waktu_habis'])
                ->default('belum_mulai');
            $table->unsignedInteger('seed');
            $table->json('urutan_soal_ids');
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('batas_waktu')->nullable();
            $table->dateTime('waktu_submit')->nullable();
            $table->decimal('skor_total', 6, 2)->nullable();
            $table->decimal('skor_maksimal', 6, 2)->nullable();
            $table->json('tab_switch_log')->nullable();
            $table->unsignedSmallInteger('tab_switch_count')->default(0);
            $table->unique(['ujian_id', 'siswa_id']);
        });

        Schema::create('ujian_attempt_jawaban', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_attempt_id')->constrained('ujian_attempt')->cascadeOnDelete();
            $table->foreignId('ujian_soal_id')->constrained('ujian_soal')->cascadeOnDelete();
            $table->foreignId('soal_bank_opsi_id')->nullable()->constrained('soal_bank_opsi')->nullOnDelete();
            $table->json('urutan_opsi_ids');
            $table->boolean('is_benar')->nullable();
            $table->decimal('poin_didapat', 5, 2)->nullable();
            $table->dateTime('dijawab_pada')->nullable();
            $table->unique(['ujian_attempt_id', 'ujian_soal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ujian_attempt_jawaban');
        Schema::dropIfExists('ujian_attempt');
        Schema::dropIfExists('ujian_soal');
        Schema::dropIfExists('ujian');
        Schema::dropIfExists('soal_bank_opsi');
        Schema::dropIfExists('soal_bank');
    }
};
