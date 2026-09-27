<?php

namespace App\Services\Reports;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\PengumpulanTugas;
use App\Models\SikapSosial;
use App\Models\SikapSpiritual;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use App\Models\WaliKelas;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

// Membangun data laporan in-app (rekap absensi, tugas, sikap, wali kelas) untuk Kepala Sekolah.
class LaporanKepsekService
{
    /**
     * Rekap persentase kehadiran per kelas.
     */
    public function buildAbsensiRekap(): Collection
    {
        $kelas = Kelas::withCount(['siswa' => fn ($q) => $q->where('status', 'aktif')])->get();
        $rekap = [];

        foreach ($kelas as $k) {
            $total = Absensi::whereHas('kelasMapel', fn ($q) => $q->where('kelas_id', $k->id)->aktif())
                ->count();
            $hadir = Absensi::whereHas('kelasMapel', fn ($q) => $q->where('kelas_id', $k->id)->aktif())
                ->where('status', 'hadir')
                ->count();

            $rekap[] = [
                'kelas' => $k,
                'total_absensi' => $total,
                'total_hadir' => $hadir,
                'persen' => $total > 0 ? round(($hadir / $total) * 100, 2) : 0,
            ];
        }

        return collect($rekap);
    }

    /**
     * Isi statistik pengumpulan (total, sudah, belum, rata nilai) ke setiap tugas dalam paginator.
     */
    public function buildTugasRekap(LengthAwarePaginator $tugas): LengthAwarePaginator
    {
        foreach ($tugas as $t) {
            $t->total_siswa = $t->pengumpulan->count();
            $t->sudah_kumpul = $t->pengumpulan->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)->count();
            $t->belum_kumpul = $t->pengumpulan->where('status', 'belum')->count();
            $t->rata_nilai = $t->pengumpulan->whereNotNull('nilai')->avg('nilai');
        }

        return $tugas;
    }

    /**
     * Rekap rata-rata sikap sosial per siswa untuk tahun ajaran & semester aktif.
     */
    public function buildSikapSosialRekap(?int $kelasId): Collection
    {
        $taAktif = TahunAjaran::getAktif();
        $semester = Pengaturan::getValue('semester_aktif', '1');

        $query = SikapSosial::with(['siswa.user', 'siswa.kelas', 'kelasMapel.mataPelajaran'])
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester);

        if ($kelasId) {
            $query->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelasId));
        }

        return $query->get()->groupBy('siswa_id')->map(function ($records) {
            $first = $records->first();

            return [
                'siswa' => $first->siswa,
                'mapel_count' => $records->count(),
                'empati' => round($records->avg('empati'), 1),
                'kerjasama' => round($records->avg('kerjasama'), 1),
                'toleransi' => round($records->avg('toleransi'), 1),
                'percaya_diri' => round($records->avg('percaya_diri'), 1),
                'komunikasi' => round($records->avg('komunikasi'), 1),
            ];
        })->values();
    }

    /**
     * Rekap rata-rata sikap spiritual per siswa untuk tahun ajaran & semester aktif.
     */
    public function buildSikapSpiritualRekap(?int $kelasId): Collection
    {
        $taAktif = TahunAjaran::getAktif();
        $semester = Pengaturan::getValue('semester_aktif', '1');

        $query = SikapSpiritual::with(['siswa.user', 'siswa.kelas', 'kelasMapel.mataPelajaran'])
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester);

        if ($kelasId) {
            $query->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelasId));
        }

        return $query->get()->groupBy('siswa_id')->map(function ($records) {
            $first = $records->first();

            return [
                'siswa' => $first->siswa,
                'mapel_count' => $records->count(),
                'taqwa' => round($records->avg('taqwa'), 1),
                'kejujuran' => round($records->avg('kejujuran'), 1),
                'disiplin' => round($records->avg('disiplin'), 1),
                'sabar' => round($records->avg('sabar'), 1),
                'syukur' => round($records->avg('syukur'), 1),
                'tawadhu' => round($records->avg('tawadhu'), 1),
            ];
        })->values();
    }

    /**
     * Bangun matriks absensi (tanggal x siswa) beserta rekap kehadiran per siswa untuk satu wali kelas.
     *
     * @return array{tanggalProps: Collection, siswaRows: Collection}
     */
    public function buildWaliKelasMatrix(WaliKelas $waliKelas, string $bulan): array
    {
        $tanggalList = $this->schoolDays($bulan);
        $siswaList = $waliKelas->kelas->siswa()
            ->with('user')
            ->where('status', 'aktif')
            ->orderBy('nis')
            ->get();

        $absensiRaw = $waliKelas->absensi()
            ->whereIn('siswa_id', $siswaList->pluck('id'))
            ->whereBetween('tanggal', ["{$bulan}-01", Carbon::createFromFormat('Y-m-d', "{$bulan}-01")->endOfMonth()->format('Y-m-d')])
            ->get();

        $absensiData = [];
        foreach ($absensiRaw as $row) {
            $absensiData[$row->siswa_id][$row->tanggal->format('Y-m-d')] = $row->status;
        }

        $tanggalProps = collect($tanggalList)->map(fn (Carbon $tanggal) => [
            'date' => $tanggal->format('Y-m-d'),
            'day' => $tanggal->format('d'),
        ]);

        $siswaRows = $siswaList->map(function ($siswa) use ($tanggalProps, $absensiData) {
            $counts = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
            $statuses = $tanggalProps->map(function (array $tanggal) use ($siswa, $absensiData, &$counts) {
                $status = $absensiData[$siswa->id][$tanggal['date']] ?? null;
                if ($status && array_key_exists($status, $counts)) {
                    $counts[$status]++;
                }

                return [
                    'date' => $tanggal['date'],
                    'status' => $status,
                    'label' => ['hadir' => 'H', 'sakit' => 'S', 'izin' => 'I', 'alpha' => 'A'][$status] ?? '-',
                ];
            });

            return [
                'id' => $siswa->id,
                'nis' => $siswa->nis,
                'nama' => $siswa->user?->nama_lengkap ?? '-',
                'statuses' => $statuses,
                'counts' => $counts,
            ];
        });

        return [
            'tanggalProps' => $tanggalProps,
            'siswaRows' => $siswaRows,
        ];
    }

    /**
     * Daftar hari sekolah (hari kerja) dalam sebulan.
     *
     * @return Carbon[]
     */
    public function schoolDays(string $bulan): array
    {
        $start = Carbon::createFromFormat('Y-m-d', "{$bulan}-01")->startOfDay();
        $end = $start->copy()->endOfMonth();
        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->isWeekday()) {
                $days[] = $date->copy();
            }
        }

        return $days;
    }

    /**
     * Opsi bulan (Juli-Juni) untuk satu tahun ajaran wali kelas.
     */
    public function waliKelasMonthOptions(WaliKelas $waliKelas, string $bulan): array
    {
        $year = (int) substr($bulan, 0, 4);
        $startYear = (int) substr((string) $waliKelas->tahunAjaran?->tahun, 0, 4);
        if (! $startYear) {
            $monthNumber = (int) substr($bulan, 5, 2);
            $startYear = $monthNumber >= 7 ? $year : $year - 1;
        }

        $labels = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $months = [];
        foreach ([7, 8, 9, 10, 11, 12, 1, 2, 3, 4, 5, 6] as $month) {
            $optionYear = $month >= 7 ? $startYear : $startYear + 1;
            $months[sprintf('%04d-%02d', $optionYear, $month)] = "{$labels[$month]} {$optionYear}";
        }

        return $months;
    }
}
