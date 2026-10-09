<?php

namespace App\Services\Reports\Exports;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\SikapSosial;
use App\Models\SikapSpiritual;
use App\Models\Siswa;
use App\Models\TahunAjaran;

final class SikapReportService
{
    use BuildsReportSlug;

    public function rekapForKelas(int $kelasId, string $semester): array
    {
        $taAktif = TahunAjaran::getAktif();
        $kelas = Kelas::findOrFail($kelasId);
        $siswaList = Siswa::with('user')->where('kelas_id', $kelasId)->where('status', 'aktif')->orderBy('nis')->get();
        $kelasMapelIds = KelasMapel::where('kelas_id', $kelasId)
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester)
            ->pluck('id');

        $labelNilai = [1 => 'TB', 2 => 'KB', 3 => 'C', 4 => 'B', 5 => 'SB'];
        $spFields = ['taqwa', 'kejujuran', 'disiplin', 'sabar', 'syukur', 'tawadhu'];
        $soFields = ['empati', 'kerjasama', 'toleransi', 'percaya_diri', 'komunikasi'];

        $spData = SikapSpiritual::whereIn('siswa_id', $siswaList->pluck('id'))
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester)
            ->whereIn('kelas_mapel_id', $kelasMapelIds)
            ->get()
            ->groupBy('siswa_id');

        $soData = SikapSosial::whereIn('siswa_id', $siswaList->pluck('id'))
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->where('semester', $semester)
            ->whereIn('kelas_mapel_id', $kelasMapelIds)
            ->get()
            ->groupBy('siswa_id');

        $rows = $siswaList->values()->map(function (Siswa $siswa, int $index) use ($spFields, $soFields, $spData, $soData, $labelNilai) {
            $sp = $spData->get($siswa->id, collect());
            $so = $soData->get($siswa->id, collect());

            return array_merge([
                $index + 1,
                $siswa->nis,
                $siswa->user?->nama_lengkap ?? '-',
            ], $this->labeledAverages($sp, $spFields, $labelNilai), $this->labeledAverages($so, $soFields, $labelNilai));
        })->all();

        return [
            'headers' => ['No', 'NIS', 'Nama', 'Taqwa', 'Jujur', 'Disiplin', 'Sabar', 'Syukur', 'Tawadhu', 'Empati', 'Kerja Sama', 'Toleransi', 'Percaya Diri', 'Komunikasi'],
            'rows' => $rows,
            'context' => "Kelas {$kelas->tingkat} {$kelas->nama_kelas}",
            'slug' => $this->slug("{$kelas->tingkat}_{$kelas->nama_kelas}_semester_{$semester}"),
            'taAktif' => $taAktif,
        ];
    }

    private function labeledAverages($records, array $fields, array $labels): array
    {
        return collect($fields)
            ->map(fn ($field) => $records->isNotEmpty() ? ($labels[(int) round($records->avg($field))] ?? '-') : '-')
            ->all();
    }
}
