<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Services\Reports\Exports\NilaiExportService;
use App\Services\Reports\Exports\TugasExportService;
use Illuminate\Http\Request;

final class ReportExcelExportController extends Controller
{
    public function __construct(
        private readonly NilaiExportService $nilai,
        private readonly TugasExportService $tugas,
    ) {}

    public function nilai(Request $request)
    {
        $filters = $this->filters($request);
        [$path, $filename] = $this->nilai->export($filters['kelas_id'], $filters['semester']);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function absensi(Request $request, ExportController $exportController)
    {
        // kelas_id/bulan kosong berarti "Semua Kelas"/"Semua Bulan" — didelegasikan ke
        // ExportController::excelAbsensi() yang menangani kedua mode (single & multi-section).
        return $exportController->excelAbsensi($request);
    }

    public function tugas(Request $request)
    {
        $filters = $this->filters($request);
        [$path, $filename] = $this->tugas->export($filters['kelas_id'], $filters['semester']);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function filters(Request $request, bool $withMonth = false): array
    {
        $rules = [
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'semester' => ['nullable', 'in:1,2'],
        ];
        if ($withMonth) {
            $rules['bulan'] = ['nullable', 'date_format:Y-m'];
        }

        $validated = $request->validate($rules);

        return [
            'kelas_id' => (int) $validated['kelas_id'],
            'semester' => (string) ($validated['semester'] ?? Pengaturan::getValue('semester_aktif', '1')),
            'bulan' => (string) ($validated['bulan'] ?? date('Y-m')),
        ];
    }
}
