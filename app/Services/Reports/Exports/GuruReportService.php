<?php

namespace App\Services\Reports\Exports;

use App\Models\Absensi;
use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\Pengaturan;
use App\Models\PengumpulanTugas;
use App\Models\SikapSosial;
use App\Models\SikapSpiritual;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Services\AttendanceScheduleService;
use Illuminate\Http\Request;

final class GuruReportService
{
    use BuildsReportSlug;

    public function nilai(KelasMapel $kelasMapel): array
    {
        $taAktif = TahunAjaran::getAktif();
        $semester = Pengaturan::getValue('semester_aktif', '1');
        $fields = ['sum1', 'sum2', 'sum3', 'sum4', 'nilai_harian', 'sts', 'sas', 'sat'];
        $nilaiList = NilaiAkhir::where('kelas_mapel_id', $kelasMapel->id)
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester)
            ->get()
            ->keyBy('siswa_id');

        $rows = Siswa::with('user')->where('kelas_id', $kelasMapel->kelas_id)->where('status', 'aktif')->orderBy('nis')->get()
            ->values()
            ->map(function (Siswa $siswa, int $index) use ($nilaiList, $fields) {
                $nilai = $nilaiList->get($siswa->id);

                return array_merge([$index + 1, $siswa->nis, $siswa->user?->nama_lengkap ?? '-'], collect($fields)->map(fn ($field) => $nilai?->{$field})->all(), [$nilai?->rata_akhir]);
            })->all();

        return $this->kelasMapelDatasetBase($kelasMapel, $taAktif, $semester) + [
            'headers' => ['No', 'NIS', 'Nama', 'SUM1', 'SUM2', 'SUM3', 'SUM4', 'Nilai Harian', 'STS', 'SAS', 'SAT', 'Rata Akhir'],
            'rows' => $rows,
        ];
    }

    public function absensi(Request $request, KelasMapel $kelasMapel): array
    {
        $request->validate(['bulan' => 'nullable|date_format:Y-m']);
        $bulan = $request->input('bulan', date('Y-m'));

        return $this->absensiHarian($kelasMapel, $bulan)
            + ['slug' => $this->slug($this->kelasMapelContext($kelasMapel)."_{$bulan}")];
    }

    public function absensiHarian(KelasMapel $kelasMapel, string $bulan): array
    {
        $meetings = AttendanceScheduleService::meetings($bulan, $kelasMapel);
        $students = Siswa::with('user')->where('kelas_id', $kelasMapel->kelas_id)->where('status', 'aktif')->orderBy('nis')->get();
        $absensiRaw = Absensi::where('kelas_mapel_id', $kelasMapel->id)
            ->whereIn('siswa_id', $students->pluck('id'))
            ->whereBetween('tanggal', ["{$bulan}-01", date('Y-m-t', strtotime("{$bulan}-01"))])
            ->get()
            ->groupBy('siswa_id')
            ->map(fn ($records) => $records->keyBy(fn (Absensi $absensi) => $absensi->tanggal?->format('Y-m-d')));

        $rows = $students->values()->map(function (Siswa $siswa, int $index) use ($meetings, $absensiRaw) {
            $row = [$index + 1, $siswa->nis, $siswa->user?->nama_lengkap ?? '-'];
            $counts = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
            $studentAbsensi = $absensiRaw->get($siswa->id, collect());

            foreach ($meetings as $meeting) {
                $status = $studentAbsensi->get($meeting['date'])?->status;
                $row[] = ['hadir' => 'H', 'sakit' => 'S', 'izin' => 'I', 'alpha' => 'A'][$status] ?? '-';
                if ($status && isset($counts[$status])) {
                    $counts[$status]++;
                }
            }

            return array_merge($row, array_values($counts));
        })->all();

        return [
            'headers' => array_merge(['No', 'NIS', 'Nama'], $meetings->map(fn ($meeting) => $meeting['title'].' '.$meeting['label'])->all(), ['H', 'S', 'I', 'A']),
            'rows' => $rows,
            'context' => $this->kelasMapelContext($kelasMapel)." - {$bulan}",
        ];
    }

    /**
     * Tabel ringkasan Hadir/Sakit/Izin/Alpha per siswa untuk satu kelas_mapel.
     * $bulan null berarti "Semua Bulan" (agregat seluruh riwayat absensi kelas ini).
     */
    public function absensiRingkasan(KelasMapel $kelasMapel, ?string $bulan, string $periodLabel): array
    {
        $students = Siswa::with('user')
            ->where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->orderBy('nis')
            ->get();
        $query = Absensi::where('kelas_mapel_id', $kelasMapel->id)
            ->whereIn('siswa_id', $students->pluck('id'));

        if ($bulan) {
            $query->whereBetween('tanggal', ["{$bulan}-01", date('Y-m-t', strtotime("{$bulan}-01"))]);
        }

        $absensiBySiswa = $query->get()->groupBy('siswa_id');
        $rows = $students->values()->map(function (Siswa $siswa, int $index) use ($absensiBySiswa) {
            $records = $absensiBySiswa->get($siswa->id, collect());
            $hadir = $records->where('status', 'hadir')->count();
            $sakit = $records->where('status', 'sakit')->count();
            $izin = $records->where('status', 'izin')->count();
            $alpha = $records->where('status', 'alpha')->count();
            $total = $hadir + $sakit + $izin + $alpha;

            return [
                $index + 1,
                $siswa->nis,
                $siswa->user?->nama_lengkap ?? '-',
                $hadir,
                $sakit,
                $izin,
                $alpha,
                $total,
                $total > 0 ? round(($hadir / $total) * 100, 2).'%' : '0%',
            ];
        })->all();

        return [
            'headers' => ['No', 'NIS', 'Nama', 'Hadir', 'Sakit', 'Izin', 'Alpha', 'Total', 'Persen Hadir'],
            'rows' => $rows,
            'context' => $this->kelasMapelContext($kelasMapel)." - {$periodLabel}",
        ];
    }

    /** Section absensi untuk satu kelas: tabel harian bila $bulan diisi, ringkasan bila "Semua Bulan". */
    public function absensiSection(KelasMapel $kelasMapel, ?string $bulan): array
    {
        return $bulan
            ? $this->absensiHarian($kelasMapel, $bulan)
            : $this->absensiRingkasan($kelasMapel, null, 'Semua Bulan');
    }

    /** Daftar Absensi guru: mendukung kelas_mapel_id kosong ("Semua Kelas") & bulan kosong ("Semua Bulan"). */
    public function absensiMulti(Request $request, ?int $guruId): array
    {
        $validated = $request->validate([
            'kelas_mapel_id' => 'nullable|integer|exists:kelas_mapel,id',
            'bulan' => 'nullable|date_format:Y-m',
        ]);
        $bulan = $validated['bulan'] ?? null;
        $kelasMapelId = $validated['kelas_mapel_id'] ?? null;

        $kelasMapelList = $kelasMapelId
            ? KelasMapel::with(['kelas', 'mataPelajaran'])->where('guru_id', $guruId)->aktif()->where('id', $kelasMapelId)->get()
            : KelasMapel::with(['kelas', 'mataPelajaran'])->where('guru_id', $guruId)->aktif()->get();

        abort_if($kelasMapelList->isEmpty(), 404);

        $sections = $kelasMapelList->map(fn (KelasMapel $km) => $this->absensiSection($km, $bulan))->all();
        $slugBase = $kelasMapelId ? $this->kelasMapelContext($kelasMapelList->first()) : 'semua_kelas';

        return [
            'sections' => $sections,
            'orientation' => $bulan ? 'landscape' : 'portrait',
            'slug' => $this->slug($slugBase.($bulan ? "_{$bulan}" : '_semua_bulan')),
        ];
    }

    /** Rekap Absensi guru: mendukung kelas_mapel_id kosong ("Semua Kelas") & mode keseluruhan ("Semua Bulan"). */
    public function rekapAbsensiMulti(Request $request, ?int $guruId): array
    {
        $validated = $request->validate([
            'kelas_mapel_id' => 'nullable|integer|exists:kelas_mapel,id',
            'mode' => 'nullable|in:bulanan,keseluruhan',
            'bulan' => 'nullable|date_format:Y-m',
        ]);
        $mode = $validated['mode'] ?? 'bulanan';
        $bulan = $mode === 'bulanan' ? ($validated['bulan'] ?? date('Y-m')) : null;
        $periodLabel = $bulan ? "Bulan {$bulan}" : 'Semua Bulan';
        $kelasMapelId = $validated['kelas_mapel_id'] ?? null;

        $kelasMapelList = $kelasMapelId
            ? KelasMapel::with(['kelas', 'mataPelajaran'])->where('guru_id', $guruId)->aktif()->where('id', $kelasMapelId)->get()
            : KelasMapel::with(['kelas', 'mataPelajaran'])->where('guru_id', $guruId)->aktif()->get();

        abort_if($kelasMapelList->isEmpty(), 404);

        $sections = $kelasMapelList->map(fn (KelasMapel $km) => $this->absensiRingkasan($km, $bulan, $periodLabel))->all();
        $slugBase = $kelasMapelId ? $this->kelasMapelContext($kelasMapelList->first()) : 'semua_kelas';

        return [
            'sections' => $sections,
            'slug' => $this->slug($slugBase.'_'.$mode.($bulan ? "_{$bulan}" : '')),
        ];
    }

    public function sikap(KelasMapel $kelasMapel): array
    {
        $taAktif = TahunAjaran::getAktif();
        $semester = Pengaturan::getValue('semester_aktif', '1');
        $spFields = ['taqwa', 'kejujuran', 'disiplin', 'sabar', 'syukur', 'tawadhu'];
        $soFields = ['empati', 'kerjasama', 'toleransi', 'percaya_diri', 'komunikasi'];
        $spiritual = SikapSpiritual::where('kelas_mapel_id', $kelasMapel->id)->where('tahun_ajaran_id', $taAktif?->id)->where('semester', $semester)->get()->keyBy('siswa_id');
        $sosial = SikapSosial::where('kelas_mapel_id', $kelasMapel->id)->where('tahun_ajaran_id', $taAktif?->id)->where('semester', $semester)->get()->keyBy('siswa_id');

        $rows = Siswa::with('user')->where('kelas_id', $kelasMapel->kelas_id)->where('status', 'aktif')->orderBy('nis')->get()
            ->values()
            ->map(function (Siswa $siswa, int $index) use ($spFields, $soFields, $spiritual, $sosial) {
                $sp = $spiritual->get($siswa->id);
                $so = $sosial->get($siswa->id);

                return array_merge(
                    [$index + 1, $siswa->nis, $siswa->user?->nama_lengkap ?? '-'],
                    collect($spFields)->map(fn ($field) => $sp?->{$field})->all(),
                    collect($soFields)->map(fn ($field) => $so?->{$field})->all()
                );
            })->all();

        return $this->kelasMapelDatasetBase($kelasMapel, $taAktif, $semester) + [
            'headers' => ['No', 'NIS', 'Nama', 'Taqwa', 'Jujur', 'Disiplin', 'Sabar', 'Syukur', 'Tawadhu', 'Empati', 'Kerja Sama', 'Toleransi', 'Percaya Diri', 'Komunikasi'],
            'rows' => $rows,
        ];
    }

    public function tugas(KelasMapel $kelasMapel): array
    {
        $totalSiswa = Siswa::where('kelas_id', $kelasMapel->kelas_id)->where('status', 'aktif')->count();
        $rows = Tugas::where('kelas_mapel_id', $kelasMapel->id)
            ->withCount(['pengumpulan as sudah_mengumpulkan' => fn ($q) => $q
                ->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
                ->whereHas('siswa', fn ($siswa) => $siswa->where('kelas_id', $kelasMapel->kelas_id)->where('status', 'aktif'))])
            ->orderBy('created_at', 'desc')
            ->get()
            ->values()
            ->map(fn (Tugas $tugas, int $index) => [
                $index + 1,
                $tugas->judul,
                $tugas->batas_waktu?->format('d/m/Y') ?? '-',
                $tugas->sudah_mengumpulkan ?? 0,
                $totalSiswa,
                $totalSiswa > 0 ? round((($tugas->sudah_mengumpulkan ?? 0) / $totalSiswa) * 100).'%' : '-',
            ])->all();

        return [
            'headers' => ['No', 'Judul', 'Deadline', 'Sudah Mengumpulkan', 'Total Siswa', 'Persen'],
            'rows' => $rows,
            'context' => $this->kelasMapelContext($kelasMapel),
            'slug' => $this->slug($this->kelasMapelContext($kelasMapel)),
        ];
    }

    public function pengumpulanTugas(KelasMapel $kelasMapel, Tugas $tugas): array
    {
        abort_unless((int) $tugas->kelas_mapel_id === (int) $kelasMapel->id, 403);

        $pengumpulan = PengumpulanTugas::with(['siswa.user', 'files'])
            ->where('tugas_id', $tugas->id)
            ->get()
            ->keyBy('siswa_id');

        $rows = Siswa::with('user')
            ->where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->orderBy('nis')
            ->get()
            ->values()
            ->map(function (Siswa $siswa, int $index) use ($pengumpulan) {
                $item = $pengumpulan->get($siswa->id);

                return [
                    $index + 1,
                    $siswa->user?->nama_lengkap ?? $siswa->nis,
                    ucfirst(str_replace('_', ' ', (string) ($item?->status ?? 'belum'))),
                    $item?->tanggal_kumpul?->format('d/m/Y H:i') ?? '-',
                    $item?->nilai ?? '-',
                    $item?->catatan ?? '-',
                    ($item?->files->count() ?? 0) + ($item?->file_upload ? 1 : 0),
                ];
            })->all();

        return [
            'headers' => ['No', 'Siswa', 'Status', 'Tanggal Kumpul', 'Nilai', 'Catatan', 'Jumlah File'],
            'rows' => $rows,
            'context' => $this->kelasMapelContext($kelasMapel).' - '.$tugas->judul,
            'slug' => $this->slug($this->kelasMapelContext($kelasMapel).'_'.$tugas->judul),
        ];
    }

    public function ujianHasil(KelasMapel $kelasMapel, Ujian $ujian): array
    {
        abort_unless((int) $ujian->kelas_mapel_id === (int) $kelasMapel->id, 403);

        $attempts = UjianAttempt::with(['siswa.user', 'jawaban'])
            ->where('ujian_id', $ujian->id)
            ->get()
            ->keyBy('siswa_id');

        $rows = Siswa::with('user')
            ->where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->orderBy('nis')
            ->get()
            ->values()
            ->map(function (Siswa $siswa, int $index) use ($attempts) {
                $att = $attempts->get($siswa->id);
                $skor100 = $att && $att->skor_maksimal > 0
                    ? round(($att->skor_total / $att->skor_maksimal) * 100, 2)
                    : ($att ? 0 : '-');

                return [
                    $index + 1,
                    $siswa->nis ?? '-',
                    $siswa->user?->nama_lengkap ?? '-',
                    $att ? ucfirst(str_replace('_', ' ', (string) $att->status)) : 'Belum Mulai',
                    $skor100,
                    $att?->waktu_mulai?->format('d/m/Y H:i') ?? '-',
                    $att?->waktu_submit?->format('d/m/Y H:i') ?? '-',
                    $att ? (int) $att->tab_switch_count : 0,
                ];
            })->all();

        return [
            'headers' => ['No', 'NIS', 'Nama Siswa', 'Status', 'Skor', 'Waktu Mulai', 'Waktu Submit', 'Tab Switch'],
            'rows' => $rows,
            'context' => $this->kelasMapelContext($kelasMapel).' - '.$ujian->judul,
            'slug' => $this->slug($this->kelasMapelContext($kelasMapel).'_'.$ujian->judul),
        ];
    }

    private function kelasMapelDatasetBase(KelasMapel $kelasMapel, ?TahunAjaran $tahunAjaran, string $semester): array
    {
        return [
            'context' => $this->kelasMapelContext($kelasMapel),
            'slug' => $this->slug($this->kelasMapelContext($kelasMapel)."_semester_{$semester}"),
            'taAktif' => $tahunAjaran,
            'semester' => $semester,
        ];
    }

    private function kelasMapelContext(KelasMapel $kelasMapel): string
    {
        $kelasMapel->loadMissing(['kelas', 'mataPelajaran']);

        return trim(($kelasMapel->kelas?->nama_kelas ?? '-').' - '.($kelasMapel->mataPelajaran?->nama_mapel ?? '-'));
    }
}
