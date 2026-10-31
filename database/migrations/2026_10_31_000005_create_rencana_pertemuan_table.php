<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rencana_pertemuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->foreignId('tujuan_pembelajaran_id')->nullable()->constrained('tujuan_pembelajaran')->nullOnDelete();
            $table->unsignedInteger('pertemuan_ke');
            $table->date('tanggal_rencana');
            $table->unsignedSmallInteger('jp')->default(1);
            $table->enum('status', ['direncanakan', 'terlaksana', 'ditunda', 'digeser'])->default('direncanakan');
            $table->date('tanggal_realisasi')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['kelas_mapel_id', 'pertemuan_ke'], 'rencana_pertemuan_scope_unique');
            $table->index(['kurikulum_id', 'tanggal_rencana']);
            $table->index(['kelas_mapel_id', 'tanggal_rencana']);
            $table->index(['tujuan_pembelajaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rencana_pertemuan');
    }
};
