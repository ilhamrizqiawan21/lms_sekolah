<?php

namespace App\Services;

use App\Models\KelasMapel;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianSoal;
use Illuminate\Support\Facades\DB;

class UjianService
{
    public function createUjian(KelasMapel $kelasMapel, array $validated): Ujian
    {
        return DB::transaction(function () use ($kelasMapel, $validated) {
            $ujian = Ujian::create([
                'kelas_mapel_id' => $kelasMapel->id,
                'judul' => $validated['judul'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'durasi_menit' => $validated['durasi_menit'],
                'kategori_nilai' => $validated['kategori_nilai'],
                'waktu_mulai' => $validated['waktu_mulai'] ?? null,
                'waktu_selesai' => $validated['waktu_selesai'] ?? null,
                'acak_soal' => $validated['acak_soal'],
                'acak_opsi' => $validated['acak_opsi'],
            ]);

            $this->replaceSoal($ujian, $validated['soal']);

            return $ujian;
        });
    }

    public function updateUjian(Ujian $ujian, array $validated): void
    {
        DB::transaction(function () use ($ujian, $validated) {
            // Lock existing attempt rows for this ujian_id so a student's mulai()
            // (which inserts a new UjianAttempt for the same ujian_id) blocks until
            // this transaction commits, instead of racing the ujianSoal delete below.
            $hasActiveAttempts = UjianAttempt::where('ujian_id', $ujian->id)
                ->where('status', '!=', UjianAttempt::STATUS_BELUM_MULAI)
                ->lockForUpdate()
                ->exists();

            $ujian->update([
                'judul' => $validated['judul'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'durasi_menit' => $validated['durasi_menit'],
                'kategori_nilai' => $validated['kategori_nilai'],
                'waktu_mulai' => $validated['waktu_mulai'] ?? null,
                'waktu_selesai' => $validated['waktu_selesai'] ?? null,
                'acak_soal' => $validated['acak_soal'],
                'acak_opsi' => $validated['acak_opsi'],
            ]);

            if ($ujian->waktu_selesai) {
                UjianAttempt::where('ujian_id', $ujian->id)
                    ->where('status', UjianAttempt::STATUS_SEDANG_MENGERJAKAN)
                    ->where('batas_waktu', '>', $ujian->waktu_selesai)
                    ->update(['batas_waktu' => $ujian->waktu_selesai]);
            }

            if (! $hasActiveAttempts && isset($validated['soal'])) {
                $ujian->ujianSoal()->delete();
                $this->replaceSoal($ujian, $validated['soal']);
            }
        });
    }

    public function hasActiveAttempts(Ujian $ujian): bool
    {
        return UjianAttempt::where('ujian_id', $ujian->id)
            ->where('status', '!=', UjianAttempt::STATUS_BELUM_MULAI)
            ->exists();
    }

    private function replaceSoal(Ujian $ujian, array $soalList): void
    {
        foreach ($soalList as $index => $soalItem) {
            UjianSoal::create([
                'ujian_id' => $ujian->id,
                'soal_bank_id' => $soalItem['soal_bank_id'],
                'poin' => $soalItem['poin'],
                'urutan' => $index + 1,
            ]);
        }
    }
}
