<?php

namespace Database\Seeders;

use App\Models\JadwalMengajar;
use App\Models\KelasMapel;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class JadwalMengajarSeeder extends Seeder
{
    /**
     * Jadwal mengajar adalah sumber tunggal tanggal pertemuan riil yang dipakai
     * AttendanceScheduleService (halaman Absensi & ekspornya). Tanpa baris di
     * sini, kelas_mapel demo tidak akan pernah menampilkan pertemuan sama sekali.
     */
    public function run(): void
    {
        $tahunAjaran = TahunAjaran::getAktif();

        if (! $tahunAjaran) {
            return;
        }

        $kelasMapelList = KelasMapel::where('tahun_ajaran_id', $tahunAjaran->id)
            ->where('semester', '1')
            ->orderBy('kelas_id')
            ->orderBy('mapel_id')
            ->get();

        $usedByGuru = [];
        $usedByKelas = [];

        foreach (JadwalMengajar::all() as $existing) {
            $usedByGuru[$existing->guru_id][$existing->hari.'-'.$existing->pelajaran_ke] = true;
            $usedByKelas[$existing->kelas_id][$existing->hari.'-'.$existing->pelajaran_ke] = true;
        }

        foreach ($kelasMapelList as $kelasMapel) {
            $alreadyScheduled = JadwalMengajar::where('kelas_mapel_id', $kelasMapel->id)->count();

            for ($i = $alreadyScheduled; $i < $kelasMapel->pertemuan_per_minggu; $i++) {
                $slot = $this->findFreeSlot((int) $kelasMapel->guru_id, (int) $kelasMapel->kelas_id, $usedByGuru, $usedByKelas);

                if (! $slot) {
                    continue;
                }

                [$hari, $pelajaranKe] = $slot;

                JadwalMengajar::create([
                    'guru_id' => $kelasMapel->guru_id,
                    'kelas_id' => $kelasMapel->kelas_id,
                    'kelas_mapel_id' => $kelasMapel->id,
                    'hari' => $hari,
                    'pelajaran_ke' => $pelajaranKe,
                ]);

                $usedByGuru[$kelasMapel->guru_id][$hari.'-'.$pelajaranKe] = true;
                $usedByKelas[$kelasMapel->kelas_id][$hari.'-'.$pelajaranKe] = true;
            }
        }
    }

    /** Cari slot (hari, pelajaran_ke) pertama yang bebas untuk guru maupun kelas. */
    private function findFreeSlot(int $guruId, int $kelasId, array $usedByGuru, array $usedByKelas): ?array
    {
        for ($hari = 1; $hari <= 5; $hari++) {
            for ($pelajaranKe = 1; $pelajaranKe <= 8; $pelajaranKe++) {
                $key = "{$hari}-{$pelajaranKe}";

                if (! empty($usedByGuru[$guruId][$key]) || ! empty($usedByKelas[$kelasId][$key])) {
                    continue;
                }

                return [$hari, $pelajaranKe];
            }
        }

        return null;
    }
}
