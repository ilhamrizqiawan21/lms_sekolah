<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\NilaiAkhir;
use App\Models\PengumpulanTugas;
use App\Models\Pengumuman;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Support\Collection;

class StatistikService
{
    /**
     * Statistik dashboard admin.
     */
    public function dashboardAdmin(): array
    {
        return [
            'total_siswa' => $this->countUsersByRole('siswa'),
            'total_guru' => $this->countUsersByRole('guru'),
            'total_kelas' => Kelas::count(),
            'total_mapel' => MataPelajaran::count(),
            'siswa_aktif' => Siswa::where('status', 'aktif')->count(),
        ];
    }

    /**
     * Statistik dashboard guru.
     */
    public function dashboardGuruForKelasMapel(Collection $kelasMapels, int $guruId): array
    {
        $kelasMapelIds = $kelasMapels->pluck('id');

        return [
            'total_kelas_mapel' => $kelasMapels->count(),
            'total_siswa' => Siswa::whereIn('kelas_id', $kelasMapels->pluck('kelas_id')->unique())
                ->where('status', 'aktif')
                ->count(),
            'total_materi' => Materi::whereIn('kelas_mapel_id', $kelasMapelIds)->count(),
            'total_tugas' => Tugas::whereIn('kelas_mapel_id', $kelasMapelIds)->count(),
            'total_pengumuman' => Pengumuman::where('created_by', $guruId)->count(),
        ];
    }

    /**
     * Statistik dashboard siswa.
     */
    public function dashboardSiswa(int $siswaId): array
    {
        $siswa = Siswa::findOrFail($siswaId);
        $activeKelasMapelIds = KelasMapel::where('kelas_id', $siswa->kelas_id)->aktif()->pluck('id');
        $totalTugas = Tugas::whereIn('kelas_mapel_id', $activeKelasMapelIds)->count();
        $tugasKumpul = PengumpulanTugas::where('siswa_id', $siswaId)
            ->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
            ->whereHas('tugas', fn ($q) => $q->whereIn('kelas_mapel_id', $activeKelasMapelIds))
            ->count();

        return [
            'total_absensi' => Absensi::where('siswa_id', $siswaId)->whereIn('kelas_mapel_id', $activeKelasMapelIds)->count(),
            'total_tugas' => $totalTugas,
            'tugas_belum' => max(0, $totalTugas - $tugasKumpul),
            'rata_rata_nilai' => NilaiAkhir::where('siswa_id', $siswaId)
                ->whereIn('kelas_mapel_id', $activeKelasMapelIds)
                ->selectRaw('AVG('.NilaiAkhir::rataAkhirExpression().') as rata_rata')
                ->value('rata_rata'),
        ];
    }

    /**
     * Statistik dashboard kepala sekolah.
     */
    public function dashboardKepsek(): array
    {
        $totalSiswa = Siswa::where('status', 'aktif')->count();
        $totalGuru = $this->countUsersByRole('guru');
        $totalKelas = Kelas::count();
        $activeKelasMapelIds = KelasMapel::aktif()->pluck('id');

        // Rata-rata nilai seluruh siswa pada periode aktif
        $rataNilai = NilaiAkhir::whereIn('kelas_mapel_id', $activeKelasMapelIds)
            ->selectRaw('AVG('.NilaiAkhir::rataAkhirExpression().') as rata_rata')
            ->value('rata_rata');

        // Persentase kehadiran pada periode aktif
        $totalAbsensi = Absensi::whereIn('kelas_mapel_id', $activeKelasMapelIds)->count();
        $totalHadir = Absensi::whereIn('kelas_mapel_id', $activeKelasMapelIds)->where('status', 'hadir')->count();
        $persenHadir = $totalAbsensi > 0 ? round(($totalHadir / $totalAbsensi) * 100, 2) : 0;

        return [
            'total_siswa' => $totalSiswa,
            'total_guru' => $totalGuru,
            'total_kelas' => $totalKelas,
            'rata_nilai' => round($rataNilai ?? 0, 2),
            'persen_hadir' => $persenHadir,
            'total_mapel' => MataPelajaran::count(),
        ];
    }

    private function countUsersByRole(string $role): int
    {
        return User::whereHas('role', fn ($query) => $query->where('nama_role', $role))->count();
    }
}
