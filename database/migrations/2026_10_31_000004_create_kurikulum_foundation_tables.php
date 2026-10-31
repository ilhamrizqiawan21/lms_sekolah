<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurikulum', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->foreignId('mapel_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('semester', ['1', '2']);
            $table->string('jenis_kurikulum', 30)->default('merdeka');
            $table->string('fase', 20)->nullable();
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->enum('status', ['draft', 'diajukan', 'disetujui', 'aktif', 'arsip'])->default('draft');
            $table->timestamps();

            $table->index(['tahun_ajaran_id', 'semester']);
            $table->index(['mapel_id', 'tahun_ajaran_id']);
            $table->index(['guru_id', 'status']);
        });

        Schema::create('capaian_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
            $table->string('kode', 50)->nullable();
            $table->string('elemen', 150)->nullable();
            $table->text('deskripsi');
            $table->unsignedInteger('urutan')->default(1);
            $table->timestamps();

            $table->index(['kurikulum_id', 'urutan']);
            $table->index(['kurikulum_id', 'kode']);
        });

        Schema::create('tujuan_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
            $table->foreignId('capaian_pembelajaran_id')->nullable()->constrained('capaian_pembelajaran')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->unsignedInteger('urutan')->default(1);
            $table->text('tujuan');
            $table->text('materi_pokok')->nullable();
            $table->text('indikator_ketercapaian')->nullable();
            $table->unsignedSmallInteger('alokasi_jp')->default(0);
            $table->unsignedSmallInteger('estimasi_pertemuan')->default(1);
            $table->string('bentuk_asesmen', 150)->nullable();
            $table->timestamps();

            $table->index(['kurikulum_id', 'urutan']);
            $table->index(['kurikulum_id', 'kode']);
            $table->index('capaian_pembelajaran_id');
        });

        Schema::create('kurikulum_kelas_mapel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kurikulum_id', 'kelas_mapel_id'], 'kurikulum_kelas_mapel_unique');
            $table->index('kelas_mapel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kurikulum_kelas_mapel');
        Schema::dropIfExists('tujuan_pembelajaran');
        Schema::dropIfExists('capaian_pembelajaran');
        Schema::dropIfExists('kurikulum');
    }
};
