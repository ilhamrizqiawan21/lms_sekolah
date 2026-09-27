<?php

namespace App\Services;

use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\Pengaturan;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TugasService
{
    public function __construct(
        protected NotifikasiService $notifikasiService,
        protected NilaiService $nilaiService,
    ) {}

    public function createTugas(KelasMapel $kelasMapel, array $validated): Tugas
    {
        $batasWaktu = Carbon::parse($validated['batas_waktu'])->endOfDay();

        return DB::transaction(function () use ($kelasMapel, $validated, $batasWaktu) {
            $tugas = Tugas::create([
                'kelas_mapel_id' => $kelasMapel->id,
                'judul' => $validated['judul'],
                'deskripsi' => $validated['deskripsi'],
                'batas_waktu' => $batasWaktu,
            ]);

            $this->notifyTugasBaru($tugas, $kelasMapel->id);

            return $tugas;
        });
    }

    /**
     * @param  Collection<int, KelasMapel>  $kelasMapelList
     */
    public function createBulkTugas(Collection $kelasMapelList, array $validated): void
    {
        $batasWaktu = Carbon::parse($validated['batas_waktu'])->endOfDay();

        DB::transaction(function () use ($kelasMapelList, $validated, $batasWaktu) {
            foreach ($kelasMapelList as $item) {
                $tugas = Tugas::create([
                    'kelas_mapel_id' => $item->id,
                    'judul' => $validated['judul'],
                    'deskripsi' => $validated['deskripsi'] ?? null,
                    'batas_waktu' => $batasWaktu,
                ]);

                $this->notifyTugasBaru($tugas, $item->id);
            }
        });
    }

    /**
     * @return array{status: 'no_change'|'saved', message: string, pengumpulan: ?PengumpulanTugas}
     */
    public function gradeSubmission(KelasMapel $kelasMapel, Tugas $tugas, Siswa $siswa, array $validated): array
    {
        $pengumpulan = PengumpulanTugas::where([
            'tugas_id' => $tugas->id,
            'siswa_id' => $siswa->id,
        ])->first();

        $nilaiInput = array_key_exists('nilai', $validated) && $validated['nilai'] !== null && $validated['nilai'] !== ''
            ? round((float) $validated['nilai'], 2)
            : null;
        $penalty = $nilaiInput !== null ? min($nilaiInput, $this->latePenaltyPoints($pengumpulan, $tugas)) : 0.0;
        $nilaiFinal = $nilaiInput !== null ? max(0, round($nilaiInput - $penalty, 2)) : null;

        if ($nilaiInput === null && blank($validated['catatan'] ?? null)) {
            return [
                'status' => 'no_change',
                'message' => 'Tidak ada nilai atau komentar yang diisi.',
                'pengumpulan' => null,
            ];
        }

        $values = [
            'catatan' => $validated['catatan'] ?? null,
        ];

        if ($nilaiInput !== null) {
            $values['nilai'] = $nilaiFinal;
            $values['nilai_sebelum_penalty'] = $nilaiInput;
            $values['penalty_terlambat'] = $penalty;
            $values['status'] = PengumpulanTugas::STATUS_DINILAI;
            $values['graded_at'] = now();
        } elseif ($pengumpulan && $pengumpulan->nilai === null
            && in_array($pengumpulan->status, PengumpulanTugas::STATUS_PERLU_DINILAI)) {
            // Komentar tanpa nilai: kembalikan ke siswa untuk diperbaiki,
            // sehingga tidak lagi masuk antrian "perlu dinilai".
            $values['status'] = PengumpulanTugas::STATUS_PERLU_PERBAIKAN;
            $values['penalty_terlambat'] = 0;
            $values['graded_at'] = now();
        } elseif (! $pengumpulan) {
            $values['status'] = PengumpulanTugas::STATUS_BELUM;
            $values['penalty_terlambat'] = 0;
            $values['graded_at'] = now();
        }

        $saved = DB::transaction(function () use ($tugas, $siswa, $values, $nilaiInput, $kelasMapel): PengumpulanTugas {
            $saved = PengumpulanTugas::updateOrCreate(
                [
                    'tugas_id' => $tugas->id,
                    'siswa_id' => $siswa->id,
                ],
                $values
            );

            if ($nilaiInput !== null) {
                $this->syncNilaiHarian($kelasMapel, $siswa);
            }

            return $saved;
        });

        return [
            'status' => 'saved',
            'message' => $nilaiInput !== null
                ? 'Nilai tugas berhasil disimpan dan nilai harian diperbarui.'
                : 'Komentar tugas berhasil disimpan.',
            'pengumpulan' => $saved,
        ];
    }

    public function latePenaltyPoints(?PengumpulanTugas $pengumpulan, Tugas $tugas): float
    {
        $daysLate = $this->lateDays($pengumpulan?->tanggal_kumpul, $tugas->batas_waktu);
        if ($daysLate <= 0) {
            return 0.0;
        }

        $pointsPerDay = max(0, round((float) Pengaturan::getValue('penalty_terlambat_poin', '1'), 2));
        if ($pointsPerDay <= 0) {
            return 0.0;
        }

        // Selisih hari kalender antara tanggal kumpul dan tanggal batas waktu.
        // 1 hari keterlambatan = 1 poin (atau sesuai pengaturan per hari).
        return round($daysLate * $pointsPerDay, 2);
    }

    public function lateDays(?Carbon $submittedAt, ?Carbon $deadline): int
    {
        // Penilaian langsung oleh guru tidak memiliki waktu pengumpulan.
        // Jangan memakai waktu sekarang sebagai waktu pengumpulan karena
        // tugas seperti hafalan akan terus dianggap terlambat.
        if (! $deadline || ! $submittedAt) {
            return 0;
        }

        return (int) max(0, $deadline->copy()->startOfDay()->diffInDays($submittedAt->copy()->startOfDay(), false));
    }

    private function notifyTugasBaru(Tugas $tugas, int $kelasMapelId): void
    {
        $this->notifikasiService->notifikasiKelasMapel(
            $kelasMapelId,
            'tugas_baru',
            'Tugas Baru',
            "Tugas '{$tugas->judul}' telah diberikan.",
            route('siswa.tugas.show', $tugas->id)
        );
    }

    private function syncNilaiHarian(KelasMapel $kelasMapel, Siswa $siswa): void
    {
        $tahunAjaran = TahunAjaran::getAktif();

        if (! $tahunAjaran) {
            return;
        }

        $semester = Pengaturan::getValue('semester_aktif', '1');
        $average = PengumpulanTugas::where('siswa_id', $siswa->id)
            ->whereNotNull('nilai')
            ->whereHas('tugas', fn ($query) => $query
                ->where('kelas_mapel_id', $kelasMapel->id)
                ->where('kategori_nilai', 'NH'))
            ->avg('nilai');

        $existing = NilaiAkhir::where([
            'siswa_id' => $siswa->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => $semester,
        ])->first();

        if ($average === null && ! $existing) {
            return;
        }

        $this->nilaiService->simpanNilai([
            'siswa_id' => $siswa->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => $semester,
            'sum1' => $existing?->sum1,
            'sum2' => $existing?->sum2,
            'sum3' => $existing?->sum3,
            'sum4' => $existing?->sum4,
            'nilai_harian' => $average !== null ? round((float) $average, 2) : null,
            'sts' => $existing?->sts,
            'sas' => $existing?->sas,
            'sat' => $existing?->sat,
        ]);
    }
}
