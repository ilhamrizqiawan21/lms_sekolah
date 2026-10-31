<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'ujian_attempt_jawaban_penilaian';
        $indexName = 'uajp_attempt_created_idx';

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($indexName): void {
                $table->id();
                $table->foreignId('ujian_attempt_jawaban_id')->constrained('ujian_attempt_jawaban')->cascadeOnDelete();
                $table->foreignId('dinilai_oleh')->constrained('users')->restrictOnDelete();
                $table->decimal('poin_sebelumnya', 5, 2)->nullable();
                $table->decimal('poin_diberikan', 5, 2);
                $table->text('catatan')->nullable();
                $table->dateTime('created_at');
                // Nama eksplisit mencegah identifier melewati batas 64 karakter MySQL.
                $table->index(['ujian_attempt_jawaban_id', 'created_at'], $indexName);
            });

            return;
        }

        // Percobaan migration sebelumnya dapat meninggalkan tabel sebelum gagal di pembuatan index.
        $hasAttemptCreatedIndex = collect(Schema::getIndexes($tableName))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === $indexName
                || ($index['columns'] ?? []) === ['ujian_attempt_jawaban_id', 'created_at']
        );

        if (! $hasAttemptCreatedIndex) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->index(['ujian_attempt_jawaban_id', 'created_at'], $indexName);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_attempt_jawaban_penilaian');
    }
};
