<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use App\Models\RaporSiswa;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RaporController extends Controller
{
    public function index(Request $request)
    {
        $semester = (string) $request->input('semester', Pengaturan::getValue('semester_aktif', '1'));
        $siswa = $request->user()->siswa;
        $tahunAjaran = TahunAjaran::getAktif();
        $rapor = $siswa && $tahunAjaran
            ? RaporSiswa::with(['siswa.user', 'siswa.kelas', 'nilai', 'tahunAjaran'])
                ->where('siswa_id', $siswa->id)
                ->where('tahun_ajaran_id', $tahunAjaran->id)
                ->where('semester', $semester)
                ->where('status', 'final')
                ->first()
            : null;

        return Inertia::render('Siswa/Rapor', [
            'semester' => $semester,
            'tahunAjaran' => $tahunAjaran?->tahun,
            'rapor' => $rapor ? [
                'nama' => $rapor->siswa?->user?->nama_lengkap ?? $rapor->siswa?->nis ?? '-',
                'nis' => $rapor->siswa?->nis ?? '-',
                'kelas' => $rapor->siswa?->kelas?->displayName() ?? '-',
                'catatan_wali_kelas' => $rapor->catatan_wali_kelas,
                'sakit' => $rapor->sakit,
                'izin' => $rapor->izin,
                'alpha' => $rapor->alpha,
                'nilai' => $rapor->nilai->map(fn ($item) => [
                    'mata_pelajaran' => $item->mata_pelajaran,
                    'nilai_akhir' => $item->nilai_akhir,
                    'predikat' => $item->predikat,
                    'deskripsi' => $item->deskripsi,
                ])->values(),
            ] : null,
        ]);
    }
}
