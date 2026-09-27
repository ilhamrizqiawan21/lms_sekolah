<?php

namespace App\Services\Reports\Exports;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\Pengaturan;
use App\Models\PengumpulanTugas;
use App\Models\SikapSosial;
use App\Models\SikapSpiritual;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use Illuminate\Http\Request;

final class KepsekReportService
{
    /**
     * DomPDF's table layout engine is memory-hungry per row (~O(n) borders/style
     * resolution per cell); a few thousand rows can exhaust even a 512MB limit.
     * Excel (OpenSpout, streaming writer) does not have this problem.
     */
    private const PDF_ROW_LIMIT = 1500;

    public function absensi(Request $request, bool $limitForPdf = false): array
    {
        $request->validate([
            'kelas_mapel_id' => 'nullable|integer|exists:kelas_mapel,id',
            'tanggal_awal' => 'nullable|date',
            'tanggal_akhir' => 'nullable|date',
            'status' => 'nullable|in:hadir,sakit,izin,alpha',
        ]);

        $tanggalAwal = $request->input('tanggal_awal');
        $tanggalAkhir = $request->input('tanggal_akhir');
        $usedDefaultRange = false;

        // Untuk export PDF tanpa filter tanggal sama sekali, batasi ke bulan berjalan
        // alih-alih menyapu seluruh riwayat absensi — itulah yang menyebabkan laporan
        // "meledak" (ribuan baris, lihat PDF_ROW_LIMIT di atas). Excel (OpenSpout,
        // streaming writer) tidak punya masalah memori ini, jadi tetap ambil riwayat
        // penuh seperti sebelumnya.
        if ($limitForPdf && ! $tanggalAwal && ! $tanggalAkhir) {
            $tanggalAwal = now()->startOfMonth()->toDateString();
            $tanggalAkhir = now()->endOfMonth()->toDateString();
            $usedDefaultRange = true;
        }

        $query = Absensi::with(['siswa.user', 'kelasMapel.kelas', 'kelasMapel.mataPelajaran'])
            ->whereHas('kelasMapel', fn ($q) => $q->aktif());

        if ($request->filled('kelas_mapel_id')) {
            $query->where('kelas_mapel_id', $request->kelas_mapel_id);
        }
        if ($tanggalAwal) {
            $query->where('tanggal', '>=', $tanggalAwal);
        }
        if ($tanggalAkhir) {
            $query->where('tanggal', '<=', $tanggalAkhir);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($limitForPdf) {
            $total = (clone $query)->count();

            abort_if($total > self::PDF_ROW_LIMIT, 422,
                "Laporan ini memiliki {$total} baris, terlalu besar untuk PDF (maks ".self::PDF_ROW_LIMIT.'). '
                .'Persempit rentang tanggal atau pilih satu kelas-mapel, atau gunakan export Excel untuk data sebesar ini.');
        }

        $rows = $query->orderBy('tanggal', 'desc')->get()->values()->map(fn (Absensi $item, int $index) => [
            $index + 1,
            $item->siswa?->user?->nama_lengkap ?? $item->siswa?->nis ?? '-',
            $item->kelasMapel?->kelas?->nama_kelas ?? '-',
            $item->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            $item->tanggal?->format('d/m/Y') ?? '-',
            ucfirst((string) $item->status),
            $item->keterangan ?? '-',
        ])->all();

        return [
            'headers' => ['No', 'Nama Siswa', 'Kelas', 'Mapel', 'Tanggal', 'Status', 'Keterangan'],
            'rows' => $rows,
            'context' => $usedDefaultRange
                ? "Bulan berjalan ({$tanggalAwal} s/d {$tanggalAkhir}) — pilih rentang tanggal untuk periode lain"
                : 'Sesuai filter laporan',
        ];
    }

    public function nilai(Request $request): array
    {
        $request->validate([
            'kelas_id' => 'nullable|integer|exists:kelas,id',
            'mapel_id' => 'nullable|integer|exists:mata_pelajaran,id',
            'semester' => 'nullable|in:1,2',
        ]);

        $taAktif = TahunAjaran::getAktif();
        $semester = $request->input('semester', Pengaturan::getValue('semester_aktif', '1'));

        $query = NilaiAkhir::with(['siswa.user', 'siswa.kelas', 'kelasMapel.kelas', 'kelasMapel.mataPelajaran'])
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester);

        if ($request->filled('kelas_id')) {
            $query->whereHas('kelasMapel', fn ($q) => $q->where('kelas_id', $request->kelas_id));
        }
        if ($request->filled('mapel_id')) {
            $query->whereHas('kelasMapel', fn ($q) => $q->where('mapel_id', $request->mapel_id));
        }

        $rows = $query->orderBy('rata_akhir', 'desc')->get()->values()->map(fn (NilaiAkhir $item, int $index) => [
            $index + 1,
            $item->siswa?->user?->nama_lengkap ?? '-',
            $item->siswa?->kelas?->nama_kelas ?? $item->kelasMapel?->kelas?->nama_kelas ?? '-',
            $item->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            $item->sum1,
            $item->sum2,
            $item->sum3,
            $item->sum4,
            $item->nilai_harian,
            $item->sts,
            $item->sas,
            $item->sat,
            $item->rata_akhir,
        ])->all();

        return [
            'headers' => ['No', 'Siswa', 'Kelas', 'Mapel', 'Sum 1', 'Sum 2', 'Sum 3', 'Sum 4', 'Nilai Harian', 'STS', 'SAS', 'SAT', 'Rata Akhir'],
            'rows' => $rows,
            'context' => 'Sesuai filter laporan',
            'taAktif' => $taAktif,
            'semester' => $semester,
        ];
    }

    public function rekapTugas(Request $request): array
    {
        $request->validate([
            'kelas_id' => 'nullable|integer|exists:kelas,id',
            'search' => 'nullable|string|max:100',
        ]);

        $query = Tugas::with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.guru', 'pengumpulan.siswa.user'])
            ->whereHas('kelasMapel', fn ($q) => $q->aktif());

        if ($request->filled('kelas_id')) {
            $query->whereHas('kelasMapel', fn ($q) => $q->where('kelas_id', $request->kelas_id));
        }
        if ($request->filled('search')) {
            $query->where('judul', 'like', '%'.$request->search.'%');
        }

        $rows = $query->orderBy('batas_waktu', 'desc')->get()->values()->map(function (Tugas $item, int $index) {
            $total = $item->pengumpulan->count();
            $sudah = $item->pengumpulan->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)->count();

            return [
                $index + 1,
                $item->judul,
                $item->kelasMapel?->kelas?->nama_kelas ?? '-',
                $item->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
                $item->kelasMapel?->guru?->nama_lengkap ?? '-',
                $item->batas_waktu?->format('d/m/Y') ?? '-',
                $total,
                $sudah,
                $total - $sudah,
                $total > 0 ? round(($sudah / $total) * 100).'%' : '-',
                $item->pengumpulan->whereNotNull('nilai')->avg('nilai') ? round($item->pengumpulan->whereNotNull('nilai')->avg('nilai'), 1) : '-',
            ];
        })->all();

        return [
            'headers' => ['No', 'Judul', 'Kelas', 'Mapel', 'Guru', 'Deadline', 'Total', 'Sudah', 'Belum', 'Persen', 'Rata Nilai'],
            'rows' => $rows,
            'context' => 'Sesuai filter laporan',
        ];
    }

    public function rekapAbsensi(): array
    {
        $kelas = Kelas::withCount(['siswa' => fn ($q) => $q->where('status', 'aktif')])->get();

        $activeKelasMapelIds = KelasMapel::aktif()->pluck('id');
        $stats = Absensi::whereIn('absensi.kelas_mapel_id', $activeKelasMapelIds)
            ->join('kelas_mapel', 'kelas_mapel.id', '=', 'absensi.kelas_mapel_id')
            ->selectRaw("kelas_mapel.kelas_id as kelas_id, count(*) as total, sum(case when absensi.status = 'hadir' then 1 else 0 end) as hadir")
            ->groupBy('kelas_mapel.kelas_id')
            ->get()
            ->keyBy('kelas_id');

        $rows = $kelas->values()->map(function (Kelas $kelas, int $index) use ($stats) {
            $stat = $stats->get($kelas->id);
            $total = (int) ($stat->total ?? 0);
            $hadir = (int) ($stat->hadir ?? 0);

            return [
                $index + 1,
                trim("{$kelas->tingkat} {$kelas->nama_kelas}"),
                (int) ($kelas->siswa_count ?? 0),
                $total,
                $hadir,
                $total > 0 ? round(($hadir / $total) * 100, 2).'%' : '0%',
            ];
        })->all();

        return [
            'headers' => ['No', 'Kelas', 'Jumlah Siswa', 'Total Absensi', 'Total Hadir', 'Persentase Hadir'],
            'rows' => $rows,
            'context' => 'Ringkasan per kelas aktif',
        ];
    }

    public function rekapSikap(Request $request): array
    {
        $request->validate(['kelas_id' => 'nullable|integer|exists:kelas,id']);

        $taAktif = TahunAjaran::getAktif();
        $semester = Pengaturan::getValue('semester_aktif', '1');
        $kelasId = $request->input('kelas_id');

        $sosialQuery = SikapSosial::with(['siswa.user', 'siswa.kelas'])
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester);
        $spiritualQuery = SikapSpiritual::with(['siswa.user', 'siswa.kelas'])
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester);

        if ($kelasId) {
            $sosialQuery->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelasId));
            $spiritualQuery->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelasId));
        }

        $sosialRows = $sosialQuery->get()->groupBy('siswa_id');
        $spiritualRows = $spiritualQuery->get()->groupBy('siswa_id');
        $siswaIds = $sosialRows->keys()->merge($spiritualRows->keys())->unique()->values();

        $rows = $siswaIds->map(function ($siswaId, int $index) use ($sosialRows, $spiritualRows) {
            $sosial = $sosialRows->get($siswaId, collect());
            $spiritual = $spiritualRows->get($siswaId, collect());
            $siswa = $sosial->first()?->siswa ?? $spiritual->first()?->siswa;

            return [
                $index + 1,
                $siswa?->user?->nama_lengkap ?? '-',
                $siswa?->kelas?->nama_kelas ?? '-',
                round($sosial->avg('empati'), 1),
                round($sosial->avg('kerjasama'), 1),
                round($sosial->avg('toleransi'), 1),
                round($sosial->avg('percaya_diri'), 1),
                round($sosial->avg('komunikasi'), 1),
                round($spiritual->avg('taqwa'), 1),
                round($spiritual->avg('kejujuran'), 1),
                round($spiritual->avg('disiplin'), 1),
                round($spiritual->avg('sabar'), 1),
                round($spiritual->avg('syukur'), 1),
                round($spiritual->avg('tawadhu'), 1),
            ];
        })->all();

        return [
            'headers' => ['No', 'Siswa', 'Kelas', 'Empati', 'Kerja Sama', 'Toleransi', 'Percaya Diri', 'Komunikasi', 'Taqwa', 'Jujur', 'Disiplin', 'Sabar', 'Syukur', 'Tawadhu'],
            'rows' => $rows,
            'context' => 'Sesuai filter laporan',
            'taAktif' => $taAktif,
            'semester' => $semester,
        ];
    }
}
