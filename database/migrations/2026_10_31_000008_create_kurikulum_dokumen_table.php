<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurikulum_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
            $table->foreignId('kelas_mapel_id')->nullable()->constrained('kelas_mapel')->nullOnDelete();
            $table->string('jenis', 30);
            $table->enum('semester', ['1', '2'])->nullable();
            $table->string('bab_key', 100)->nullable();
            $table->string('judul', 180);
            $table->json('isi');
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamps();

            $table->index(['kurikulum_id', 'jenis']);
            $table->index(['kurikulum_id', 'semester']);
            $table->index(['kelas_mapel_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kurikulum_dokumen');
    }
};
