<?php

namespace App\Services;

use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\Siswa;
use App\Models\SoalBank;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianAttemptJawaban;
use App\Models\UjianSoal;
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

            $tipeSoal = $soalBank?->tipe_soal ?? SoalBank::TYPE_PILIHAN_GANDA;
            $isBenar = false;
            $poinDidapat = 0.0;

            if ($tipeSoal === SoalBank::TYPE_URAIAN) {
                // Essay is graded by the teacher after submission. Preserve a manual grade
                // while keeping unanswered essays out of the automatically-correct count.
                $poinDidapat = $jawaban->poin_didapat !== null ? min($poinMaksimal, max(0.0, (float) $jawaban->poin_didapat)) : null;
                $isBenar = $poinDidapat !== null ? $poinDidapat >= $poinMaksimal : null;
            } elseif ($tipeSoal === SoalBank::TYPE_MENJODOHKAN && $soalBank) {
                $jumlahPasangan = $soalBank->opsi->count();
                $jawabanPasangan = is_array($jawaban->jawaban_pasangan) ? $jawaban->jawaban_pasangan : [];
                $jumlahBenar = $soalBank->opsi->filter(function ($opsi) use ($jawabanPasangan): bool {
                    $pilihan = $jawabanPasangan[(string) $opsi->id] ?? $jawabanPasangan[$opsi->id] ?? null;

                    return $pilihan !== null && (int) $pilihan === (int) $opsi->id;
                })->count();

                $isBenar = $jumlahPasangan > 0 && $jumlahBenar === $jumlahPasangan;
                $poinDidapat = $jumlahPasangan > 0
                    ? round($poinMaksimal * ($jumlahBenar / $jumlahPasangan), 2)
                    : 0.0;
            } elseif ($jawaban->soal_bank_opsi_id && $soalBank) {
                $opsiBenar = $soalBank->opsi->firstWhere('is_benar', true);
                $isBenar = $opsiBenar && (int) $opsiBenar->id === (int) $jawaban->soal_bank_opsi_id;
                $poinDidapat = $isBenar ? $poinMaksimal : 0.0;
            }

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
     * Recalculate a submitted attempt after a teacher grades an essay answer.
     */
    public function recalculateScores(UjianAttempt $attempt): UjianAttempt
    {
        return DB::transaction(function () use ($attempt) {
            UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)->lockForUpdate()->get();
            $attempt->unsetRelation('jawaban');

            $hasil = $this->hitungSkor($attempt);
            $attempt->update([
                'skor_total' => $hasil['skor_total'],
                'skor_maksimal' => $hasil['skor_maksimal'],
            ]);

            $this->syncNilaiUjian($attempt);

            return $attempt->fresh();
        });
    }

    /**
     * Submit ujian secara manual oleh siswa.
     */
    public function submit(UjianAttempt $attempt, ?string $alasanSelesai = null): UjianAttempt
    {
        return DB::transaction(function () use ($attempt, $alasanSelesai) {
            // Lock this attempt's answer rows so a concurrent jawab() write (a plain
            // UPDATE) blocks until this transaction commits, instead of landing
            // between this read and the score/status update and being silently lost.
            UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)->lockForUpdate()->get();
            $attempt->unsetRelation('jawaban');

            $hasil = $this->hitungSkor($attempt);

            $attempt->update([
                'status' => UjianAttempt::STATUS_SELESAI,
                'waktu_submit' => Carbon::now(),
                'alasan_selesai' => $alasanSelesai,
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

        $avgScore = $ujian->kategori_nilai === 'NH'
            ? $this->nilaiService->hitungNilaiHarian($kelasMapel, $attempt->siswa)
            : $this->hitungRataRataCbt($attempt, $kelasMapel, $ujian);

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
            $payload['nilai_harian'] = $avgScore ?? 0.0;
        } elseif ($ujian->kategori_nilai === 'STS') {
            $payload['sts'] = $avgScore;
        } elseif ($ujian->kategori_nilai === 'SAS') {
            $payload['sas'] = $avgScore;
        } elseif ($ujian->kategori_nilai === 'SAT') {
            $payload['sat'] = $avgScore;
        }

        $this->nilaiService->simpanNilai($payload);
    }

    private function hitungRataRataCbt(UjianAttempt $attempt, KelasMapel $kelasMapel, Ujian $ujian): float
    {
        $scores = UjianAttempt::query()
            ->where('siswa_id', $attempt->siswa_id)
            ->whereIn('status', UjianAttempt::STATUS_TERKUNCI)
            ->whereHas('ujian', fn ($query) => $query
                ->where('kelas_mapel_id', $kelasMapel->id)
                ->where('kategori_nilai', $ujian->kategori_nilai))
            ->get(['skor_total', 'skor_maksimal'])
            ->map(fn (UjianAttempt $item) => $item->skor_maksimal > 0
                ? round(($item->skor_total / $item->skor_maksimal) * 100, 2)
                : 0.0);

        return $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : 0.0;
    }

    /**
     * Mulai attempt baru: acak urutan soal/opsi secara deterministik (seed = attempt id)
     * lalu siapkan baris jawaban kosong untuk setiap soal.
     */
    public function startAttempt(Ujian $ujian, Siswa $siswa): UjianAttempt
    {
        return DB::transaction(function () use ($ujian, $siswa) {
            $now = Carbon::now();
            $durasi = (int) $ujian->durasi_menit;
            $batasWaktu = (clone $now)->addMinutes($durasi);

            if ($ujian->waktu_selesai && $batasWaktu->gt($ujian->waktu_selesai)) {
                $batasWaktu = clone $ujian->waktu_selesai;
            }

            $attempt = UjianAttempt::create([
                'ujian_id' => $ujian->id,
                'siswa_id' => $siswa->id,
                'status' => UjianAttempt::STATUS_SEDANG_MENGERJAKAN,
                'seed' => 0,
                'urutan_soal_ids' => [],
                'waktu_mulai' => $now,
                'batas_waktu' => $batasWaktu,
                'heartbeat_terakhir' => $now,
                'skor_total' => 0,
                'skor_maksimal' => 0,
                'tab_switch_log' => [],
                'tab_switch_count' => 0,
            ]);

            $seed = (int) $attempt->id;
            $attempt->seed = $seed;

            $ujianSoalList = UjianSoal::with('soalBank.opsi')
                ->where('ujian_id', $ujian->id)
                ->orderBy('urutan')
                ->get();

            $soalIds = $ujianSoalList->pluck('id')->all();

            if ($ujian->acak_soal) {
                $soalIds = $this->deterministicShuffle($soalIds, $seed);
            }

            $attempt->urutan_soal_ids = $soalIds;
            $attempt->save();

            // Buat baris jawaban awal dengan urutan opsi yang deterministik.
            foreach ($ujianSoalList as $us) {
                $opsiList = $us->soalBank?->opsi ?? collect();
                $opsiIds = $opsiList->sortBy('urutan')->pluck('id')->all();

                if ($ujian->acak_opsi && count($opsiIds) > 1) {
                    $opsiIds = $this->deterministicShuffle($opsiIds, $seed + (int) $us->id);
                }

                UjianAttemptJawaban::create([
                    'ujian_attempt_id' => $attempt->id,
                    'ujian_soal_id' => $us->id,
                    'soal_bank_opsi_id' => null,
                    'urutan_opsi_ids' => $opsiIds,
                    'is_benar' => null,
                    'poin_didapat' => null,
                    'dijawab_pada' => null,
                ]);
            }

            return $attempt;
        });
    }

    /**
     * Fisher-Yates shuffle dengan seed, agar urutan soal/opsi konsisten
     * setiap kali dimuat ulang untuk attempt yang sama.
     */
    private function deterministicShuffle(array $ids, int $seed): array
    {
        mt_srand($seed);
        for ($i = count($ids) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }

        return $ids;
    }

    /**
     * Konversi skor mentah menjadi persentase 0-100.
     */
    public function skorPersen(?float $skorTotal, ?float $skorMaksimal): float
    {
        if (! $skorMaksimal || $skorMaksimal <= 0) {
            return 0.0;
        }

        return round(($skorTotal / $skorMaksimal) * 100, 2);
    }
}
