<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class AbsensiService
{
    /**
     * Ambil rekap absensi per siswa.
     */
    public function rekapSiswa(int $siswaId, ?int $kelasMapelId = null): Collection
    {
        $query = Absensi::where('siswa_id', $siswaId);

        if ($kelasMapelId) {
            $query->where('kelas_mapel_id', $kelasMapelId);
        }

        $absensi = $query->get();

        $rekap = [
            'hadir' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpha' => 0,
            'total' => $absensi->count(),
        ];

        foreach ($absensi as $a) {
            if (isset($rekap[$a->status])) {
                $rekap[$a->status]++;
            }
        }

        $rekap['persen_hadir'] = $rekap['total'] > 0
            ? round(($rekap['hadir'] / $rekap['total']) * 100, 2)
            : 0;

        return collect($rekap);
    }

    /**
     * Rekap absensi untuk seluruh kelas.
     */
    public function rekapKelas(int $kelasMapelId): Collection
    {
        $siswas = Siswa::whereHas('kelas.kelasMapel', function ($q) use ($kelasMapelId) {
            $q->where('kelas_mapel.id', $kelasMapelId);
        })->where('status', 'aktif')->get();

        return $siswas->map(function ($siswa) use ($kelasMapelId) {
            return [
                'siswa' => $siswa,
                'rekap' => $this->rekapSiswa($siswa->id, $kelasMapelId),
            ];
        });
    }
}
