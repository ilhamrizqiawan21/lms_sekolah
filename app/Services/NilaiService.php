<?php

namespace App\Services;

use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\PengumpulanTugas;
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

    /**
     * Sinkronisasi nilai komponen otomatis dari pengumpulan tugas dan ujian CBT
     * ke tabel nilai_akhir tanpa saling menimpa antara Tugas dan Ujian CBT.
     */
    public function sinkronNilaiKomponen(KelasMapel $kelasMapel, int $siswaId, string $kategori = 'NH'): void
    {
        $tahunAjaranId = $kelasMapel->tahun_ajaran_id;
        $semester = $kelasMapel->semester;

        if (! $tahunAjaranId || ! $semester) {
            return;
        }

        $tugasScores = PengumpulanTugas::query()
            ->where('siswa_id', $siswaId)
            ->whereNotNull('nilai')
            ->whereHas('tugas', fn ($q) => $q
                ->where('kelas_mapel_id', $kelasMapel->id)
                ->where('kategori_nilai', $kategori))
            ->pluck('nilai')
            ->map(fn ($v) => (float) $v);

        $cbtScores = UjianAttempt::query()
            ->where('siswa_id', $siswaId)
            ->whereIn('status', UjianAttempt::STATUS_TERKUNCI)
            ->whereHas('ujian', fn ($q) => $q
                ->where('kelas_mapel_id', $kelasMapel->id)
                ->where('kategori_nilai', $kategori))
            ->get()
            ->map(fn (UjianAttempt $att) => $att->skor_maksimal > 0
                ? round(((float) $att->skor_total / (float) $att->skor_maksimal) * 100, 2)
                : 0.0);

        $allScores = $tugasScores->concat($cbtScores);
        $average = $allScores->isNotEmpty() ? round((float) $allScores->avg(), 2) : null;

        $existing = NilaiAkhir::where([
            'siswa_id' => $siswaId,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $tahunAjaranId,
            'semester' => $semester,
        ])->first();

        if ($average === null && ! $existing) {
            return;
        }

        $payload = [
            'siswa_id' => $siswaId,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $tahunAjaranId,
            'semester' => $semester,
            'sum1' => $existing?->sum1,
            'sum2' => $existing?->sum2,
            'sum3' => $existing?->sum3,
            'sum4' => $existing?->sum4,
            'nilai_harian' => $existing?->nilai_harian,
            'sts' => $existing?->sts,
            'sas' => $existing?->sas,
            'sat' => $existing?->sat,
        ];

        $fieldMap = [
            'NH' => 'nilai_harian',
            'STS' => 'sts',
            'SAS' => 'sas',
            'SAT' => 'sat',
        ];

        if (isset($fieldMap[$kategori])) {
            $payload[$fieldMap[$kategori]] = $average;
        }

        $this->simpanNilai($payload);
    }
}
