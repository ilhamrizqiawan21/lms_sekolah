<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wali_kelas_id')->constrained('wali_kelas')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->enum('semester', ['1', '2']);
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->text('catatan_wali_kelas')->nullable();
            $table->unsignedInteger('sakit')->default(0);
            $table->unsignedInteger('izin')->default(0);
            $table->unsignedInteger('alpha')->default(0);
            $table->timestamps();

            $table->unique(['siswa_id', 'tahun_ajaran_id', 'semester'], 'rapor_siswa_scope_unique');
            $table->index(['wali_kelas_id', 'status']);
        });

        Schema::create('rapor_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapor_siswa_id')->constrained('rapor_siswa')->cascadeOnDelete();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->restrictOnDelete();
            $table->string('mata_pelajaran', 150);
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('predikat', 40)->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->unique(['rapor_siswa_id', 'kelas_mapel_id'], 'rapor_nilai_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_nilai');
        Schema::dropIfExists('rapor_siswa');
    }
};
