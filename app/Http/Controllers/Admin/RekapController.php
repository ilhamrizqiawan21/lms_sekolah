<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Services\RekapService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RekapController extends Controller
{
    public function __construct(private readonly RekapService $rekapService) {}

    public function absensi(Request $request)
    {
        $request->validate(['kelas_id' => 'nullable|exists:kelas,id', 'bulan' => 'nullable|date_format:Y-m', 'semester' => 'nullable|in:1,2']);
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();
        $kelasId = $request->input('kelas_id');
        $bulan = $request->input('bulan', date('Y-m'));
        $semester = $request->input('semester', Pengaturan::getValue('semester_aktif', '1'));

        ['rekap' => $rekap, 'tanggalList' => $tanggalList, 'kelasNama' => $kelasNama] = $this->rekapService->buildAbsensiRekap($kelasId, $bulan, $semester);

        return Inertia::render('Admin/Rekap', compact('kelasList', 'rekap', 'tanggalList', 'kelasNama', 'bulan', 'kelasId', 'semester') + ['type' => 'absensi', 'title' => 'Rekap Absensi']);
    }

    public function nilai(Request $request)
    {
        $request->validate(['kelas_id' => 'nullable|exists:kelas,id', 'semester' => 'nullable|in:1,2']);
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();
        $kelasId = $request->input('kelas_id');
        $semester = $request->input('semester', Pengaturan::getValue('semester_aktif', '1'));

        ['rekap' => $rekap, 'mapelList' => $mapelList, 'kelasNama' => $kelasNama] = $this->rekapService->buildNilaiRekap($kelasId, $semester);

        return Inertia::render('Admin/Rekap', compact('kelasList', 'rekap', 'mapelList', 'kelasNama', 'kelasId', 'semester') + ['type' => 'nilai', 'title' => 'Rekap Nilai']);
    }

    public function sikap(Request $request)
    {
        $request->validate(['kelas_id' => 'nullable|exists:kelas,id', 'semester' => 'nullable|in:1,2']);
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();
        $kelasId = $request->input('kelas_id');
        $semester = $request->input('semester', Pengaturan::getValue('semester_aktif', '1'));

        ['rekap' => $rekap, 'kelasNama' => $kelasNama] = $this->rekapService->buildSikapRekap($kelasId, $semester);

        return Inertia::render('Admin/Rekap', compact('kelasList', 'rekap', 'kelasNama', 'kelasId', 'semester') + ['type' => 'sikap', 'title' => 'Rekap Sikap']);
    }

    public function tugas(Request $request)
    {
        $request->validate(['kelas_id' => 'nullable|exists:kelas,id', 'semester' => 'nullable|in:1,2']);
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();
        $kelasId = $request->input('kelas_id');
        $semester = $request->input('semester', Pengaturan::getValue('semester_aktif', '1'));

        ['tugasList' => $tugasList, 'kelasNama' => $kelasNama] = $this->rekapService->buildTugasRekap($kelasId, $semester);

        return Inertia::render('Admin/Rekap', compact('kelasList', 'tugasList', 'kelasNama', 'kelasId', 'semester') + ['type' => 'tugas', 'title' => 'Rekap Tugas']);
    }
}
