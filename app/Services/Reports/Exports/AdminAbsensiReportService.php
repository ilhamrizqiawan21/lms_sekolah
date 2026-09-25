<?php

namespace App\Services\Reports\Exports;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;

final class AdminAbsensiReportService
{
    use BuildsReportSlug;

    /** Bangun section per kelas untuk export absensi admin saat kelas_id/bulan kosong ("Semua"). */
    public function multiDataset(array $filters): array
    {
        $taAktif = TahunAjaran::getAktif();
        $semester = $filters['semester'];
        $bulan = $filters['bulan'];

        $kelasList = $filters['kelas_id']
            ? Kelas::where('id', $filters['kelas_id'])->get()
            : Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();

        abort_if($kelasList->isEmpty(), 404);

        $sections = $kelasList->map(fn (Kelas $kelas) => $bulan
            ? $this->harianSection($kelas, $bulan, $semester, $taAktif)
            : $this->ringkasanSection($kelas, $semester, $taAktif)
        )->all();

        $slugBase = $filters['kelas_id'] ? "{$kelasList->first()->tingkat}_{$kelasList->first()->nama_kelas}" : 'semua_kelas';

        return [
            'sections' => $sections,
            'taAktif' => $taAktif,
            'slug' => $this->slug($slugBase.($bulan ? "_{$bulan}" : '_semua_bulan')),
        ];
    }

    private function harianSection(Kelas $kelas, string $bulan, string $semester, ?TahunAjaran $taAktif): array
    {
        $siswaList = Siswa::with('user')->where('kelas_id', $kelas->id)->where('status', 'aktif')->orderBy('nis')->get();
        $scope = fn ($q) => $q->where('kelas_id', $kelas->id)->where('tahun_ajaran_id', $taAktif?->id)->where('semester', $semester);

        $tanggalList = Absensi::whereHas('kelasMapel', $scope)
            ->whereBetween('tanggal', ["{$bulan}-01", date('Y-m-t', strtotime("{$bulan}-01"))])
            ->orderBy('tanggal')->pluck('tanggal')->unique()->map(fn ($d) => $d->format('Y-m-d'))->values();

        $absensiData = Absensi::whereIn('siswa_id', $siswaList->pluck('id'))
            ->whereHas('kelasMapel', $scope)
            ->whereBetween('tanggal', ["{$bulan}-01", date('Y-m-t', strtotime("{$bulan}-01"))])
            ->get()->groupBy('siswa_id');

        $rows = $siswaList->values()->map(function (Siswa $siswa, int $index) use ($tanggalList, $absensiData) {
            $sa = $absensiData->get($siswa->id, collect());
            $row = [$index + 1, $siswa->nis, $siswa->user?->nama_lengkap ?? '-'];
            $counts = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
            foreach ($tanggalList as $tgl) {
                $status = $sa->firstWhere('tanggal', $tgl)?->status;
                $row[] = ['hadir' => 'H', 'sakit' => 'S', 'izin' => 'I', 'alpha' => 'A'][$status] ?? '-';
                if ($status && isset($counts[$status])) {
                    $counts[$status]++;
                }
            }

            return array_merge($row, array_values($counts));
        })->all();

        return [
            'headers' => array_merge(['No', 'NIS', 'Nama'], $tanggalList->map(fn ($tgl) => date('d/m', strtotime($tgl)))->all(), ['H', 'S', 'I', 'A']),
            'rows' => $rows,
            'context' => "Kelas {$kelas->tingkat} {$kelas->nama_kelas} - Bulan {$bulan}",
        ];
    }

    private function ringkasanSection(Kelas $kelas, string $semester, ?TahunAjaran $taAktif): array
    {
        $siswaList = Siswa::with('user')->where('kelas_id', $kelas->id)->where('status', 'aktif')->orderBy('nis')->get();
        $scope = fn ($q) => $q->where('kelas_id', $kelas->id)->where('tahun_ajaran_id', $taAktif?->id)->where('semester', $semester);

        $absensiData = Absensi::whereIn('siswa_id', $siswaList->pluck('id'))
            ->whereHas('kelasMapel', $scope)
            ->get()->groupBy('siswa_id');

        $rows = $siswaList->values()->map(function (Siswa $siswa, int $index) use ($absensiData) {
            $records = $absensiData->get($siswa->id, collect());
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
            'context' => "Kelas {$kelas->tingkat} {$kelas->nama_kelas} - Semua Bulan",
        ];
    }
}
