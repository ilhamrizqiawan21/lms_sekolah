<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->foreignId('rencana_pertemuan_id')->nullable()->constrained('rencana_pertemuan')->nullOnDelete();
            $table->date('tanggal');
            $table->unsignedInteger('pertemuan_ke')->default(1);
            $table->unsignedSmallInteger('jp')->default(1);
            $table->text('materi_pokok')->nullable();
            $table->text('tujuan_pembelajaran')->nullable();
            $table->text('kegiatan_pendahuluan')->nullable();
            $table->text('kegiatan_inti')->nullable();
            $table->text('kegiatan_penutup')->nullable();
            $table->text('hasil_pengamatan')->nullable();
            $table->text('refleksi')->nullable();
            $table->text('ket_kehadiran')->nullable();
            $table->unsignedInteger('jumlah_siswa')->default(0);
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamps();

            $table->unique(['kelas_mapel_id', 'tanggal'], 'jurnal_harian_scope_unique');
            $table->unique('rencana_pertemuan_id', 'jurnal_harian_rencana_unique');
            $table->index(['guru_id', 'tanggal']);
            $table->index(['tahun_ajaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_harian');
    }
};
