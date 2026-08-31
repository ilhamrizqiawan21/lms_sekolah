<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\KelasMapel;
use App\Models\Notifikasi;
use App\Models\PengumpulanTugas;
use App\Models\Pengumuman;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Services\StatistikService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected StatistikService $statistikService;

    public function __construct(StatistikService $statistikService)
    {
        $this->statistikService = $statistikService;
    }

    public function index()
    {
        // fitur fitur dalam dashboard
        $guruId = Auth::id();
        $statistik = $this->statistikService->dashboardGuru($guruId);

        $kelasMapel = KelasMapel::with(['kelas', 'mataPelajaran', 'tahunAjaran'])
            ->withCount(['materi', 'tugas'])
            ->where('guru_id', $guruId)
            ->aktif()
            ->get();

        $kelasMapelIds = $kelasMapel->pluck('id');
        $kelasIds = $kelasMapel->pluck('kelas_id')->unique()->values();
        $chartMonths = $this->chartMonths();
        $tugasBelumDikumpulkan = $this->tugasBelumDikumpulkan($kelasMapel, $kelasMapelIds, $kelasIds);
        $siswaJarangMasuk = $this->siswaJarangMasuk($kelasMapelIds);
        $tugasPerluDinilai = $this->tugasPerluDinilai($kelasMapelIds);
        $kehadiranChart = $this->kehadiranChart($kelasMapelIds, $chartMonths);
        $pengumpulanTugasChart = $this->pengumpulanTugasChart($kelasMapelIds, $chartMonths);

        $pengumuman = Pengumuman::with('creator')
            ->where(function ($q) use ($kelasMapelIds, $kelasIds) {
                $q->where('target', 'semua')
                    ->orWhere('target', 'guru')
                    ->orWhere(function ($q) use ($kelasMapelIds, $kelasIds) {
                        $q->where('target', 'kelas_mapel')
                            ->where(function ($q) use ($kelasMapelIds, $kelasIds) {
                                $q->whereIn('kelas_mapel_id', $kelasMapelIds);

                                foreach ($kelasIds as $kelasId) {
                                    $q->orWhere('target_kelas', 'like', '%"'.$kelasId.'"%');
                                }
                            });
                    });
            })
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $notifikasi = Notifikasi::where('user_id', $guruId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $unreadNotifCount = Notifikasi::where('user_id', $guruId)
            ->where('is_read', false)
            ->count();

        return Inertia::render('Guru/Dashboard', [
            'statistik' => $statistik,
            'kelasMapel' => $kelasMapel->map(fn (KelasMapel $item) => [
                'id' => $item->id,
                'kelas' => $item->kelas?->nama_kelas ?? '-',
                'mata_pelajaran' => $item->mataPelajaran?->nama_mapel ?? '-',
                'semester' => $item->semester === '1' ? 'Ganjil' : 'Genap',
                'workspace_url' => route('guru.kelas-mapel.show', $item),
                'materi_count' => (int) $item->materi_count,
                'tugas_count' => (int) $item->tugas_count,
            ])->values(),
            'tugasBelumDikumpulkan' => $tugasBelumDikumpulkan,
            'siswaJarangMasuk' => $siswaJarangMasuk,
            'tugasPerluDinilai' => $tugasPerluDinilai,
            'kehadiranChart' => $kehadiranChart,
            'pengumpulanTugasChart' => $pengumpulanTugasChart,
            'pengumuman' => $pengumuman->map(fn (Pengumuman $item) => [
                'id' => $item->id,
                'judul' => $item->judul,
                'created_at' => $item->created_at ? Carbon::parse($item->created_at)->format('d M Y') : null,
            ])->values(),
            'notifikasi' => $notifikasi->map(fn (Notifikasi $item) => [
                'id' => $item->id,
                'judul' => $item->judul,
                'pesan' => $item->pesan,
                'is_read' => (bool) $item->is_read,
                'created_at' => $item->created_at ? Carbon::parse($item->created_at)->diffForHumans() : null,
            ])->values(),
            'unreadNotifCount' => $unreadNotifCount,
        ]);
    }

    private function tugasBelumDikumpulkan($kelasMapel, $kelasMapelIds, $kelasIds)
    {
        if ($kelasMapelIds->isEmpty()) {
            return collect();
        }

        $totalSiswaByKelas = Siswa::whereIn('kelas_id', $kelasIds)
            ->where('status', 'aktif')
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');

        $kelasMapelById = $kelasMapel->keyBy('id');

        return Tugas::with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran'])
            ->whereIn('kelas_mapel_id', $kelasMapelIds)
            ->whereNotNull('batas_waktu')
            ->where('batas_waktu', '<', now())
            ->orderByDesc('batas_waktu')
            ->get()
            ->map(function (Tugas $tugas) use ($totalSiswaByKelas, $kelasMapelById) {
                $kelasMapel = $kelasMapelById->get($tugas->kelas_mapel_id);
                $totalSiswa = (int) ($totalSiswaByKelas[$kelasMapel?->kelas_id] ?? 0);
                $sudahMengumpulkan = PengumpulanTugas::where('tugas_id', $tugas->id)
                    ->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
                    ->whereHas('siswa', fn ($query) => $query
                        ->where('kelas_id', $kelasMapel?->kelas_id)
                        ->where('status', 'aktif'))
                    ->count();
                $belum = max(0, $totalSiswa - $sudahMengumpulkan);

                if ($belum <= 0) {
                    return null;
                }

                return [
                    'id' => $tugas->id,
                    'judul' => $tugas->judul,
                    'kelas' => $tugas->kelasMapel?->kelas?->nama_kelas ?? '-',
                    'mata_pelajaran' => $tugas->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
                    'belum' => $belum,
                    'total_siswa' => $totalSiswa,
                    'batas_waktu' => $tugas->batas_waktu?->format('d M Y'),
                    'url' => $kelasMapel ? route('guru.tugas.pengumpulan', [$kelasMapel, $tugas]) : route('guru.tugas.index'),
                ];
            })
            ->filter()
            ->sortByDesc('belum')
            ->take(5)
            ->values();
    }

    private function siswaJarangMasuk($kelasMapelIds)
    {
        if ($kelasMapelIds->isEmpty()) {
            return collect();
        }

        $since = now()->subDays(60)->toDateString();

        return Absensi::query()
            ->join('siswa', 'siswa.id', '=', 'absensi.siswa_id')
            ->join('users', 'users.id', '=', 'siswa.user_id')
            ->join('kelas', 'kelas.id', '=', 'siswa.kelas_id')
            ->whereIn('absensi.kelas_mapel_id', $kelasMapelIds)
            ->where('siswa.status', 'aktif')
            ->where('absensi.tanggal', '>=', $since)
            ->select([
                'siswa.id',
                'siswa.nis',
                'users.nama_lengkap',
                'kelas.nama_kelas',
            ])
            ->selectRaw('COUNT(*) as total_absensi')
            ->selectRaw("SUM(CASE WHEN absensi.status = 'hadir' THEN 1 ELSE 0 END) as total_hadir")
            ->selectRaw("SUM(CASE WHEN absensi.status = 'alpha' THEN 1 ELSE 0 END) as total_alpha")
            ->groupBy('siswa.id', 'siswa.nis', 'users.nama_lengkap', 'kelas.nama_kelas')
            ->havingRaw('COUNT(*) >= 3')
            ->havingRaw("(SUM(CASE WHEN absensi.status = 'hadir' THEN 1 ELSE 0 END) / COUNT(*)) < 0.75")
            ->orderByRaw("(SUM(CASE WHEN absensi.status = 'hadir' THEN 1 ELSE 0 END) / COUNT(*)) ASC")
            ->orderByDesc(DB::raw("SUM(CASE WHEN absensi.status = 'alpha' THEN 1 ELSE 0 END)"))
            ->take(5)
            ->get()
            ->map(function ($item) {
                $totalAbsensi = (int) $item->total_absensi;
                $totalHadir = (int) $item->total_hadir;

                return [
                    'id' => $item->id,
                    'nama' => $item->nama_lengkap,
                    'nis' => $item->nis,
                    'kelas' => $item->nama_kelas,
                    'persen_hadir' => $totalAbsensi > 0 ? round(($totalHadir / $totalAbsensi) * 100) : 0,
                    'total_absensi' => $totalAbsensi,
                    'total_alpha' => (int) $item->total_alpha,
                    'url' => route('guru.absensi.index'),
                ];
            })
            ->values();
    }

    private function tugasPerluDinilai($kelasMapelIds)
    {
        if ($kelasMapelIds->isEmpty()) {
            return collect();
        }

        return PengumpulanTugas::query()
            ->join('tugas', 'tugas.id', '=', 'pengumpulan_tugas.tugas_id')
            ->join('kelas_mapel', 'kelas_mapel.id', '=', 'tugas.kelas_mapel_id')
            ->join('kelas', 'kelas.id', '=', 'kelas_mapel.kelas_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id', '=', 'kelas_mapel.mapel_id')
            ->whereIn('tugas.kelas_mapel_id', $kelasMapelIds)
            ->whereIn('pengumpulan_tugas.status', ['sudah', 'terlambat'])
            ->whereNull('pengumpulan_tugas.nilai')
            ->select([
                'tugas.id',
                'tugas.kelas_mapel_id',
                'tugas.judul',
                'kelas.nama_kelas',
                'mata_pelajaran.nama_mapel',
            ])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('tugas.id', 'tugas.kelas_mapel_id', 'tugas.judul', 'kelas.nama_kelas', 'mata_pelajaran.nama_mapel')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'judul' => $item->judul,
                'kelas' => $item->nama_kelas,
                'mata_pelajaran' => $item->nama_mapel,
                'total' => (int) $item->total,
                'url' => route('guru.tugas.pengumpulan', [$item->kelas_mapel_id, $item->id]),
            ])
            ->values();
    }

    private function chartMonths()
    {
        $today = now();
        $startYear = $today->month >= 7 ? $today->year : $today->year - 1;
        $start = Carbon::create($startYear, 7, 1)->startOfMonth();

        return collect(range(0, 11))
            ->map(fn (int $monthsFromStart) => $start->copy()->addMonths($monthsFromStart));
    }

    private function kehadiranChart($kelasMapelIds, $chartMonths)
    {
        if ($kelasMapelIds->isEmpty()) {
            return collect();
        }

        $start = $chartMonths->first();
        $monthExpression = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', tanggal)"
            : "DATE_FORMAT(tanggal, '%Y-%m')";
        $records = Absensi::whereIn('kelas_mapel_id', $kelasMapelIds)
            ->where('tanggal', '>=', $start->toDateString())
            ->select(
                DB::raw("$monthExpression as bulan"),
                DB::raw("SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir"),
                DB::raw("SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit"),
                DB::raw("SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin"),
                DB::raw("SUM(CASE WHEN status = 'alpha' THEN 1 ELSE 0 END) as alpha"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get()
            ->keyBy('bulan');

        return $chartMonths->map(function (Carbon $month) use ($records) {
            $record = $records->get($month->format('Y-m'));
            $hadir = (int) ($record->hadir ?? 0);
            $sakit = (int) ($record->sakit ?? 0);
            $izin = (int) ($record->izin ?? 0);
            $alpha = (int) ($record->alpha ?? 0);
            $total = (int) ($record->total ?? 0);

            return [
                'bulan' => $month->format('Y-m'),
                'bulan_label' => $month->format('M Y'),
                'hadir' => $hadir,
                'sakit' => $sakit,
                'izin' => $izin,
                'alpha' => $alpha,
                'total' => $total,
                'persen_hadir' => $total > 0 ? round(($hadir / $total) * 100, 1) : 0,
            ];
        })->values();
    }

    private function pengumpulanTugasChart($kelasMapelIds, $chartMonths)
    {
        if ($kelasMapelIds->isEmpty()) {
            return collect();
        }

        $start = $chartMonths->first();
        $end = $chartMonths->last()->copy()->endOfMonth();
        $tugas = Tugas::query()
            ->whereIn('kelas_mapel_id', $kelasMapelIds)
            ->whereNotNull('batas_waktu')
            ->where('batas_waktu', '>=', $start)
            ->where('batas_waktu', '<=', $end)
            ->get(['id', 'kelas_mapel_id', 'batas_waktu']);

        $kelasIds = KelasMapel::whereIn('id', $kelasMapelIds)->pluck('kelas_id')->unique()->filter();

        $totalSiswaByKelas = Siswa::whereIn('kelas_id', $kelasIds)
            ->where('status', 'aktif')
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');

        $kelasIdByKelasMapel = KelasMapel::whereIn('id', $kelasMapelIds)->pluck('kelas_id', 'id');
        $collectedByTask = PengumpulanTugas::whereIn('tugas_id', $tugas->pluck('id'))
                ->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
                ->selectRaw('tugas_id, COUNT(*) as total')
                ->groupBy('tugas_id')
                ->pluck('total', 'tugas_id');

        $monthly = $tugas->groupBy(fn (Tugas $item) => Carbon::parse($item->batas_waktu)->format('Y-m'))
            ->map(function ($items) use ($kelasIdByKelasMapel, $totalSiswaByKelas, $collectedByTask) {
                $total = $items->sum(function (Tugas $item) use ($kelasIdByKelasMapel, $totalSiswaByKelas) {
                    $kelasId = $kelasIdByKelasMapel[$item->kelas_mapel_id] ?? null;

                    return (int) ($totalSiswaByKelas[$kelasId] ?? 0);
                });
                $collected = $items->sum(fn (Tugas $item) => (int) ($collectedByTask[$item->id] ?? 0));

                return [
                    'collected' => $collected,
                    'total' => $total,
                ];
            });

        return $chartMonths->map(function (Carbon $month) use ($monthly) {
            $stats = $monthly->get($month->format('Y-m'), ['collected' => 0, 'total' => 0]);
            $collected = (int) $stats['collected'];
            $total = (int) $stats['total'];

            return [
                'bulan' => $month->format('Y-m'),
                'bulan_label' => $month->format('M Y'),
                'collected' => $collected,
                'total' => $total,
                'belum' => max(0, $total - $collected),
                'persen_dikumpulkan' => $total > 0 ? round(($collected / $total) * 100, 1) : 0,
            ];
        })->values();
    }
}
