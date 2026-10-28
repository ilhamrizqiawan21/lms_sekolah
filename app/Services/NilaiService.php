<?php

namespace App\Services;

use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\UjianAttempt;

class NilaiService
{
    /**
     * Hitung rata-rata akhir berdasarkan komponen nilai Kurikulum Merdeka.
     * Rumus: rata-rata semua komponen yang telah diisi.
     * NOTE: Kolom rata_akhir di MySQL adalah GENERATED ALWAYS AS STORED,
     * jadi MySQL akan menghitungnya otomatis. Service ini digunakan untuk
     * kebutuhan display/logika saja.
     */
    public function hitungRataAkhir(NilaiAkhir $nilai): float
    {
        $komponen = [
            $nilai->sum1,
            $nilai->sum2,
            $nilai->sum3,
            $nilai->sum4,
            $nilai->nilai_harian,
            $nilai->sts,
            $nilai->sas,
            $nilai->sat,
        ];

        $filtered = array_filter($komponen, fn ($v) => ! is_null($v));
        $total = array_sum($filtered);
        $jumlah = count($filtered);

        if ($jumlah === 0) {
            return 0;
        }

        return round($total / $jumlah, 2);
    }

    /**
     * Simpan nilai akhir. rata_akhir dihitung otomatis oleh MySQL (GENERATED COLUMN).
     */
    public function simpanNilai(array $data): NilaiAkhir
    {
        // Hapus rata_akhir dari data karena MySQL akan menghitung otomatis
        unset($data['rata_akhir']);

        return NilaiAkhir::updateOrCreate(
            [
                'siswa_id' => $data['siswa_id'],
                'kelas_mapel_id' => $data['kelas_mapel_id'],
                'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                'semester' => $data['semester'],
            ],
            $data
        );
    }

    /**
     * Hitung nilai harian dari rata-rata tugas NH dan attempt CBT NH.
     */
    public function hitungNilaiHarian(KelasMapel $kelasMapel, Siswa $siswa): ?float
    {
        $taskAverage = PengumpulanTugas::query()
            ->where('siswa_id', $siswa->id)
            ->whereNotNull('nilai')
            ->whereHas('tugas', fn ($query) => $query
                ->where('kelas_mapel_id', $kelasMapel->id)
                ->where('kategori_nilai', 'NH'))
            ->avg('nilai');

        $cbtAverage = UjianAttempt::query()
            ->where('siswa_id', $siswa->id)
            ->whereIn('status', UjianAttempt::STATUS_TERKUNCI)
            ->whereHas('ujian', fn ($query) => $query
                ->where('kelas_mapel_id', $kelasMapel->id)
                ->where('kategori_nilai', 'NH'))
            ->get(['skor_total', 'skor_maksimal'])
            ->map(fn (UjianAttempt $attempt) => $attempt->skor_maksimal > 0
                ? round(($attempt->skor_total / $attempt->skor_maksimal) * 100, 2)
                : 0.0)
            ->avg();

        $components = collect([$taskAverage, $cbtAverage])
            ->filter(fn ($value) => $value !== null)
            ->values();

        return $components->isNotEmpty() ? round((float) $components->avg(), 2) : null;
    }

    /**
     * Dapatkan predikat nilai.
     */
    public function getPredikat(float $nilai): string
    {
        return match (true) {
            $nilai >= 92 => 'A (Sangat Baik)',
            $nilai >= 83 => 'B (Baik)',
            $nilai >= 75 => 'C (Cukup)',
            default => 'D (Kurang)',
        };
    }
}
