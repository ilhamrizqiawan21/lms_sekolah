<?php

namespace App\Services;

use App\Models\NilaiAkhir;
use App\Models\UjianAttempt;
use App\Models\UjianAttemptJawaban;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CbtScoringService
{
    public function __construct(
        protected NilaiService $nilaiService
    ) {}

    /**
     * Hitung skor attempt berdasarkan jawaban siswa dan kunci jawaban.
     */
    public function hitungSkor(UjianAttempt $attempt): array
    {
        $attempt->loadMissing(['jawaban.ujianSoal.soalBank.opsi']);

        $skorTotal = 0.0;
        $skorMaksimal = 0.0;

        foreach ($attempt->jawaban as $jawaban) {
            $ujianSoal = $jawaban->ujianSoal;
            $soalBank = $ujianSoal?->soalBank;
            $poinMaksimal = (float) ($ujianSoal?->poin ?? 1.0);
            $skorMaksimal += $poinMaksimal;

            $isBenar = false;
            if ($jawaban->soal_bank_opsi_id && $soalBank) {
                $opsiBenar = $soalBank->opsi->firstWhere('is_benar', true);
                if ($opsiBenar && (int) $opsiBenar->id === (int) $jawaban->soal_bank_opsi_id) {
                    $isBenar = true;
                }
            }

            $poinDidapat = $isBenar ? $poinMaksimal : 0.0;
            $skorTotal += $poinDidapat;

            $jawaban->update([
                'is_benar' => $isBenar,
                'poin_didapat' => $poinDidapat,
            ]);
        }

        return [
            'skor_total' => round($skorTotal, 2),
            'skor_maksimal' => round($skorMaksimal, 2),
        ];
    }

    /**
     * Submit ujian secara manual oleh siswa.
     */
    public function submit(UjianAttempt $attempt): UjianAttempt
    {
        return DB::transaction(function () use ($attempt) {
            // Lock this attempt's answer rows so a concurrent jawab() write (a plain
            // UPDATE) blocks until this transaction commits, instead of landing
            // between this read and the score/status update and being silently lost.
            UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)->lockForUpdate()->get();
            $attempt->unsetRelation('jawaban');

            $hasil = $this->hitungSkor($attempt);

            $attempt->update([
                'status' => UjianAttempt::STATUS_SELESAI,
                'waktu_submit' => Carbon::now(),
                'skor_total' => $hasil['skor_total'],
                'skor_maksimal' => $hasil['skor_maksimal'],
            ]);

            $this->syncNilaiUjian($attempt);

            return $attempt->fresh();
        });
    }

    /**
     * Auto-submit saat batas waktu pengerjaan berakhir.
     */
    public function autoSubmitKarenaWaktuHabis(UjianAttempt $attempt): UjianAttempt
    {
        return DB::transaction(function () use ($attempt) {
            // See submit(): lock answer rows before scoring to avoid a race with jawab().
            UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)->lockForUpdate()->get();
            $attempt->unsetRelation('jawaban');

            $hasil = $this->hitungSkor($attempt);

            $attempt->update([
                'status' => UjianAttempt::STATUS_WAKTU_HABIS,
                'waktu_submit' => Carbon::now(),
                'skor_total' => $hasil['skor_total'],
                'skor_maksimal' => $hasil['skor_maksimal'],
            ]);

            $this->syncNilaiUjian($attempt);

            return $attempt->fresh();
        });
    }

    /**
     * Sinkronisasi skor attempt ke tabel nilai_akhir sesuai kategori_nilai ujian.
     */
    protected function syncNilaiUjian(UjianAttempt $attempt): void
    {
        $attempt->loadMissing(['ujian.kelasMapel']);
        $ujian = $attempt->ujian;
        $kelasMapel = $ujian?->kelasMapel;

        if (! $ujian || ! $kelasMapel) {
            return;
        }

        $siswaId = $attempt->siswa_id;

        // Ambil semua attempt yang terkunci (selesai atau waktu_habis) untuk kategori nilai yang sama di kelas_mapel ini
        $lockedAttempts = UjianAttempt::query()
            ->where('siswa_id', $siswaId)
            ->whereIn('status', UjianAttempt::STATUS_TERKUNCI)
            ->whereHas('ujian', function ($q) use ($kelasMapel, $ujian) {
                $q->where('kelas_mapel_id', $kelasMapel->id)
                    ->where('kategori_nilai', $ujian->kategori_nilai);
            })
            ->get();

        $scores = $lockedAttempts->map(function (UjianAttempt $att) {
            if ($att->skor_maksimal > 0) {
                return round(($att->skor_total / $att->skor_maksimal) * 100, 2);
            }

            return 0.0;
        });

        $avgScore = $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : 0.0;

        $existing = NilaiAkhir::where([
            'siswa_id' => $siswaId,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $kelasMapel->tahun_ajaran_id,
            'semester' => $kelasMapel->semester,
        ])->first();

        $payload = [
            'siswa_id' => $siswaId,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $kelasMapel->tahun_ajaran_id,
            'semester' => $kelasMapel->semester,
            'sum1' => $existing?->sum1,
            'sum2' => $existing?->sum2,
            'sum3' => $existing?->sum3,
            'sum4' => $existing?->sum4,
            'nilai_harian' => $existing?->nilai_harian,
            'sts' => $existing?->sts,
            'sas' => $existing?->sas,
            'sat' => $existing?->sat,
        ];

        if ($ujian->kategori_nilai === 'NH') {
            $payload['nilai_harian'] = $avgScore;
        } elseif ($ujian->kategori_nilai === 'STS') {
            $payload['sts'] = $avgScore;
        } elseif ($ujian->kategori_nilai === 'SAS') {
            $payload['sas'] = $avgScore;
        } elseif ($ujian->kategori_nilai === 'SAT') {
            $payload['sat'] = $avgScore;
        }

        $this->nilaiService->simpanNilai($payload);
    }
}
