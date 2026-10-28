<?php

namespace App\Services\Reports\Exports;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Tugas;

/**
 * Dataset builders for the admin "detail per kelas" PDF reports (Nilai/Absensi/Tugas),
 * which render through their own dedicated Blade views (exports.pdf.nilai/absensi/tugas)
 * instead of the generic table renderer.
 */
final class AdminDetailReportService
{
    public function __construct(private readonly ReportSchoolProfile $schoolProfile) {}

    public function nilaiDataset(int $kelasId, string $semester): array
    {
        $taAktif = TahunAjaran::getAktif();
        $kelas = Kelas::findOrFail($kelasId);
        $siswaList = Siswa::with('user')->where('kelas_id', $kelasId)->where('status', 'aktif')->orderBy('nis')->get();
        $mapelList = $this->mapelListUntukKelas($kelasId, $taAktif?->id, $semester);

        $nilaiData = NilaiAkhir::whereIn('siswa_id', $siswaList->pluck('id'))
            ->where('tahun_ajaran_id', $taAktif?->id)->where('semester', $semester)
            ->get()->groupBy('siswa_id');

        $rekap = [];
        foreach ($siswaList as $s) {
            $sn = $nilaiData->get($s->id, collect());
            $row = ['nis' => $s->nis, 'nama' => $s->user->nama_lengkap ?? '-', 'nilai' => []];
            foreach ($mapelList as $mp) {
                $n = $sn->firstWhere('kelas_mapel_id', $mp->kelas_mapel_id);
                $row['nilai'][$mp->id] = $n ? $n->rata_akhir : null;
            }
            $validNilai = array_filter($row['nilai'], fn ($v) => ! is_null($v));
            $row['rata'] = count($validNilai) > 0 ? round(array_sum($validNilai) / count($validNilai), 2) : null;
            $rekap[] = $row;
        }

        $labelSemester = $semester == '1' ? 'Ganjil' : 'Genap';

        return [
            'rekap' => $rekap,
            'mapelList' => $mapelList,
            'kelas' => $kelas,
            'labelSemester' => $labelSemester,
            'taAktif' => $taAktif,
            'reportSchool' => $this->schoolProfile->build($taAktif, $semester),
            'filename' => "rekap_nilai_{$kelas->tingkat}_{$kelas->nama_kelas}_semester_{$semester}.pdf",
        ];
    }

    public function absensiDataset(int $kelasId, string $bulan, string $semester): array
    {
        $taAktif = TahunAjaran::getAktif();
        $kelas = Kelas::findOrFail($kelasId);
        $siswaList = Siswa::with('user')->where('kelas_id', $kelasId)->where('status', 'aktif')->orderBy('nis')->get();

        $scope = fn ($q) => $q
            ->where('kelas_id', $kelasId)
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester);

        $tanggalList = Absensi::whereHas('kelasMapel', $scope)
            ->whereBetween('tanggal', ["{$bulan}-01", date('Y-m-t', strtotime("{$bulan}-01"))])
            ->orderBy('tanggal')->pluck('tanggal')->unique()->map(fn ($d) => $d->format('Y-m-d'))->values();

        $absensiData = Absensi::whereIn('siswa_id', $siswaList->pluck('id'))
            ->whereHas('kelasMapel', $scope)
            ->whereBetween('tanggal', ["{$bulan}-01", date('Y-m-t', strtotime("{$bulan}-01"))])
            ->get()->groupBy('siswa_id');

        $rekap = [];
        foreach ($siswaList as $s) {
            $sa = $absensiData->get($s->id, collect());
            $row = ['nis' => $s->nis, 'nama' => $s->user->nama_lengkap ?? '-', 'absensi' => [], 'hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
            foreach ($tanggalList as $tgl) {
                $ab = $sa->firstWhere('tanggal', $tgl);
                $st = $ab ? $ab->status : null;
                $row['absensi'][$tgl] = $st;
                if ($st) {
                    $row[$st]++;
                }
            }
            $rekap[] = $row;
        }

        $bulanIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $namaBulan = $bulanIndo[(int) substr($bulan, 5, 2)].' '.substr($bulan, 0, 4);
        $labelSemester = $semester == '1' ? 'Ganjil' : 'Genap';

        return [
            'rekap' => $rekap,
            'tanggalList' => $tanggalList,
            'kelas' => $kelas,
            'namaBulan' => $namaBulan,
            'labelSemester' => $labelSemester,
            'taAktif' => $taAktif,
            'reportSchool' => $this->schoolProfile->build($taAktif, $semester),
            'filename' => "rekap_absensi_{$kelas->tingkat}_{$kelas->nama_kelas}_{$bulan}.pdf",
        ];
    }

    public function tugasDataset(int $kelasId, string $semester): array
    {
        $taAktif = TahunAjaran::getAktif();
        $kelas = Kelas::findOrFail($kelasId);
        $totalSiswa = Siswa::where('kelas_id', $kelasId)->where('status', 'aktif')->count();

        $tugasList = Tugas::with(['kelasMapel.mataPelajaran', 'kelasMapel.guru'])
            ->whereHas('kelasMapel', fn ($q) => $q->where('kelas_id', $kelasId)->where('tahun_ajaran_id', $taAktif?->id)->where('semester', $semester))
            ->withCount(['pengumpulan as sudah_kumpul' => fn ($q) => $q
                ->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
                ->whereHas('siswa', fn ($siswa) => $siswa->where('kelas_id', $kelasId)->where('status', 'aktif'))])
            ->orderBy('created_at', 'desc')
            ->get();

        $labelSemester = $semester == '1' ? 'Ganjil' : 'Genap';

        return [
            'tugasList' => $tugasList,
            'kelas' => $kelas,
            'labelSemester' => $labelSemester,
            'taAktif' => $taAktif,
            'totalSiswa' => $totalSiswa,
            'reportSchool' => $this->schoolProfile->build($taAktif, $semester),
            'filename' => "rekap_tugas_{$kelas->tingkat}_{$kelas->nama_kelas}_semester_{$semester}.pdf",
        ];
    }

    private function mapelListUntukKelas(int|string|null $kelasId, int|string|null $tahunAjaranId, string $semester)
    {
        return KelasMapel::with('mataPelajaran')
            ->where('kelas_id', $kelasId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('semester', $semester)
            ->join('mata_pelajaran', 'mata_pelajaran.id', '=', 'kelas_mapel.mapel_id')
            ->orderBy('mata_pelajaran.urutan')
            ->orderBy('mata_pelajaran.nama_mapel')
            ->select('kelas_mapel.*')
            ->get()
            ->map(function ($kelasMapel) {
                return (object) [
                    'id' => $kelasMapel->mapel_id,
                    'kelas_mapel_id' => $kelasMapel->id,
                    'nama_mapel' => $kelasMapel->mataPelajaran?->nama_mapel ?? '-',
                ];
            });
    }
}
