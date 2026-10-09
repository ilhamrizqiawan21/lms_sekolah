<?php

namespace App\Http\Controllers;

use App\Models\KelasMapel;
use App\Models\Pengaturan;
use App\Models\Tugas;
use App\Models\Ujian;
use App\Services\Reports\Exports\AbsensiExportService;
use App\Services\Reports\Exports\AdminAbsensiReportService;
use App\Services\Reports\Exports\AdminDetailReportService;
use App\Services\Reports\Exports\GuruReportService;
use App\Services\Reports\Exports\KepsekReportService;
use App\Services\Reports\Exports\LegacyTableExcelWriter;
use App\Services\Reports\Exports\NilaiExportService;
use App\Services\Reports\Exports\SikapReportService;
use App\Services\Reports\Exports\TablePdfRenderer;
use App\Services\Reports\Exports\TugasExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * HTTP-only glue for report exports: validate the request, ask a Reports\Exports
 * service for the dataset, then render it via LegacyTableExcelWriter/TablePdfRenderer
 * (generic table exports) or a dedicated Blade view (Nilai/Absensi/Tugas detail PDFs).
 * Business/query logic lives in app/Services/Reports/Exports/*, not here.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly NilaiExportService $nilaiExport,
        private readonly AbsensiExportService $absensiExport,
        private readonly TugasExportService $tugasExport,
        private readonly AdminAbsensiReportService $adminAbsensi,
        private readonly AdminDetailReportService $adminDetail,
        private readonly SikapReportService $sikapReport,
        private readonly KepsekReportService $kepsekReport,
        private readonly GuruReportService $guruReport,
        private readonly LegacyTableExcelWriter $excel,
        private readonly TablePdfRenderer $pdf,
    ) {}

    // ─────────────────────────────────────────────
    // EXPORT EXCEL - REKAP NILAI
    // ─────────────────────────────────────────────
    public function excelNilai(Request $request)
    {
        $filters = $this->validatedExportFilters($request);
        [$path, $filename] = $this->nilaiExport->export($filters['kelas_id'], $filters['semester']);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    // ─────────────────────────────────────────────
    // EXPORT EXCEL - REKAP ABSENSI
    // ─────────────────────────────────────────────
    public function excelAbsensi(Request $request)
    {
        $filters = $this->validatedAbsensiExportFilters($request);

        if ($filters['kelas_id'] && $filters['bulan']) {
            [$path, $filename] = $this->absensiExport->export($filters['kelas_id'], $filters['semester'], $filters['bulan']);

            return response()->download($path, $filename)->deleteFileAfterSend(true);
        }

        $dataset = $this->adminAbsensi->multiDataset($filters);

        return $this->excel->multiTable('rekap_absensi_'.$dataset['slug'].'.xlsx', 'REKAP ABSENSI', $dataset['sections'], $dataset['taAktif'], $filters['semester']);
    }

    // ─────────────────────────────────────────────
    // EXPORT EXCEL - REKAP TUGAS
    // ─────────────────────────────────────────────
    public function excelTugas(Request $request)
    {
        $filters = $this->validatedExportFilters($request);
        [$path, $filename] = $this->tugasExport->export($filters['kelas_id'], $filters['semester']);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    // ─────────────────────────────────────────────
    // EXPORT PDF - REKAP NILAI
    // ─────────────────────────────────────────────
    public function pdfNilai(Request $request)
    {
        $filters = $this->validatedExportFilters($request);
        $data = $this->adminDetail->nilaiDataset($filters['kelas_id'], $filters['semester']);

        $pdf = Pdf::loadView('exports.pdf.nilai', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($data['filename']);
    }

    // ─────────────────────────────────────────────
    // EXPORT PDF - REKAP ABSENSI
    // ─────────────────────────────────────────────
    public function pdfAbsensi(Request $request)
    {
        $filters = $this->validatedAbsensiExportFilters($request);

        if ($filters['kelas_id'] && $filters['bulan']) {
            $data = $this->adminDetail->absensiDataset((int) $filters['kelas_id'], $filters['bulan'], $filters['semester']);
            $pdf = Pdf::loadView('exports.pdf.absensi', $data);
            $pdf->setPaper('A4', 'landscape');

            return $pdf->download($data['filename']);
        }

        $dataset = $this->adminAbsensi->multiDataset($filters);

        return $this->pdf->multiTable('rekap_absensi_'.$dataset['slug'].'.pdf', 'REKAP ABSENSI', $dataset['sections'], $dataset['taAktif'], $filters['semester'], null, $filters['bulan'] ? 'landscape' : 'portrait');
    }

    /** Filter export absensi admin: kelas_id & bulan sama-sama nullable ("Semua Kelas" / "Semua Bulan"). */
    private function validatedAbsensiExportFilters(Request $request): array
    {
        $validated = $request->validate([
            'kelas_id' => 'nullable|integer|exists:kelas,id',
            'semester' => 'nullable|in:1,2',
            'bulan' => 'nullable|date_format:Y-m',
        ]);

        return [
            'kelas_id' => isset($validated['kelas_id']) ? (int) $validated['kelas_id'] : null,
            'semester' => $validated['semester'] ?? Pengaturan::getValue('semester_aktif', '1'),
            'bulan' => $validated['bulan'] ?? null,
        ];
    }

    // ─────────────────────────────────────────────
    // EXPORT PDF - REKAP TUGAS
    // ─────────────────────────────────────────────
    public function pdfTugas(Request $request)
    {
        $filters = $this->validatedExportFilters($request);
        $data = $this->adminDetail->tugasDataset($filters['kelas_id'], $filters['semester']);

        $pdf = Pdf::loadView('exports.pdf.tugas', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($data['filename']);
    }

    public function excelSikap(Request $request)
    {
        $filters = $this->validatedExportFilters($request);
        $dataset = $this->sikapReport->rekapForKelas($filters['kelas_id'], $filters['semester']);

        return $this->excel->table('rekap_sikap_'.$dataset['slug'].'.xlsx', 'REKAP SIKAP', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $filters['semester']);
    }

    public function pdfSikap(Request $request)
    {
        $filters = $this->validatedExportFilters($request);
        $dataset = $this->sikapReport->rekapForKelas($filters['kelas_id'], $filters['semester']);

        return $this->pdf->table('rekap_sikap_'.$dataset['slug'].'.pdf', 'REKAP SIKAP', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $filters['semester']);
    }

    public function kepsekAbsensiExcel(Request $request)
    {
        $dataset = $this->kepsekReport->absensi($request);

        return $this->excel->table('laporan_absensi.xlsx', 'LAPORAN ABSENSI', $dataset['context'], $dataset['headers'], $dataset['rows']);
    }

    public function kepsekAbsensiPdf(Request $request)
    {
        $dataset = $this->kepsekReport->absensi($request, limitForPdf: true);

        return $this->pdf->table('laporan_absensi.pdf', 'LAPORAN ABSENSI', $dataset['context'], $dataset['headers'], $dataset['rows'], null, null, null, 'portrait');
    }

    public function kepsekNilaiExcel(Request $request)
    {
        $dataset = $this->kepsekReport->nilai($request);

        return $this->excel->table('laporan_nilai.xlsx', 'LAPORAN NILAI', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $dataset['semester']);
    }

    public function kepsekNilaiPdf(Request $request)
    {
        $dataset = $this->kepsekReport->nilai($request);

        return $this->pdf->table('laporan_nilai.pdf', 'LAPORAN NILAI', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $dataset['semester']);
    }

    public function kepsekRekapTugasExcel(Request $request)
    {
        $dataset = $this->kepsekReport->rekapTugas($request);

        return $this->excel->table('rekap_tugas.xlsx', 'REKAP TUGAS', $dataset['context'], $dataset['headers'], $dataset['rows']);
    }

    public function kepsekRekapTugasPdf(Request $request)
    {
        $dataset = $this->kepsekReport->rekapTugas($request);

        return $this->pdf->table('rekap_tugas.pdf', 'REKAP TUGAS', $dataset['context'], $dataset['headers'], $dataset['rows']);
    }

    public function kepsekRekapAbsensiExcel(Request $request)
    {
        $dataset = $this->kepsekReport->rekapAbsensi();

        return $this->excel->table('rekap_absensi.xlsx', 'REKAP ABSENSI', $dataset['context'], $dataset['headers'], $dataset['rows']);
    }

    public function kepsekRekapAbsensiPdf(Request $request)
    {
        $dataset = $this->kepsekReport->rekapAbsensi();

        return $this->pdf->table('rekap_absensi.pdf', 'REKAP ABSENSI', $dataset['context'], $dataset['headers'], $dataset['rows'], null, null, null, 'portrait');
    }

    public function kepsekRekapSikapExcel(Request $request)
    {
        $dataset = $this->kepsekReport->rekapSikap($request);

        return $this->excel->table('rekap_sikap.xlsx', 'REKAP SIKAP', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $dataset['semester']);
    }

    public function kepsekRekapSikapPdf(Request $request)
    {
        $dataset = $this->kepsekReport->rekapSikap($request);

        return $this->pdf->table('rekap_sikap.pdf', 'REKAP SIKAP', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $dataset['semester']);
    }

    public function guruNilaiExcel(Request $request, KelasMapel $kelasMapel)
    {
        $dataset = $this->guruReport->nilai($kelasMapel);

        return $this->excel->table('nilai_'.$dataset['slug'].'.xlsx', 'DAFTAR NILAI', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $dataset['semester']);
    }

    public function guruNilaiPdf(Request $request, KelasMapel $kelasMapel)
    {
        $dataset = $this->guruReport->nilai($kelasMapel);

        return $this->pdf->table(
            'nilai_'.$dataset['slug'].'.pdf',
            'DAFTAR NILAI',
            $dataset['context'],
            $dataset['headers'],
            $dataset['rows'],
            $dataset['taAktif'],
            $dataset['semester'],
            $this->pdf->teacherSigner($request)
        );
    }

    public function guruAbsensiExcel(Request $request, KelasMapel $kelasMapel)
    {
        $dataset = $this->guruReport->absensi($request, $kelasMapel);

        return $this->excel->table('absensi_'.$dataset['slug'].'.xlsx', 'DAFTAR ABSENSI', $dataset['context'], $dataset['headers'], $dataset['rows']);
    }

    public function guruAbsensiPdf(Request $request, KelasMapel $kelasMapel)
    {
        $dataset = $this->guruReport->absensi($request, $kelasMapel);

        return $this->pdf->table('absensi_'.$dataset['slug'].'.pdf', 'DAFTAR ABSENSI', $dataset['context'], $dataset['headers'], $dataset['rows'], null, null, $this->pdf->teacherSigner($request), 'landscape');
    }

    // ─────────────────────────────────────────────
    // EXPORT - DAFTAR ABSENSI (semua kelas / semua bulan)
    // ─────────────────────────────────────────────
    public function guruAbsensiExportExcel(Request $request)
    {
        $dataset = $this->guruReport->absensiMulti($request, $request->user()?->id);

        return $this->excel->multiTable('absensi_'.$dataset['slug'].'.xlsx', 'DAFTAR ABSENSI', $dataset['sections']);
    }

    public function guruAbsensiExportPdf(Request $request)
    {
        $dataset = $this->guruReport->absensiMulti($request, $request->user()?->id);

        return $this->pdf->multiTable('absensi_'.$dataset['slug'].'.pdf', 'DAFTAR ABSENSI', $dataset['sections'], null, null, $this->pdf->teacherSigner($request), $dataset['orientation']);
    }

    public function guruRekapAbsensiExcel(Request $request)
    {
        $dataset = $this->guruReport->rekapAbsensiMulti($request, $request->user()?->id);

        return $this->excel->multiTable('rekap_absensi_'.$dataset['slug'].'.xlsx', 'REKAP ABSENSI', $dataset['sections']);
    }

    public function guruRekapAbsensiPdf(Request $request)
    {
        $dataset = $this->guruReport->rekapAbsensiMulti($request, $request->user()?->id);

        return $this->pdf->multiTable('rekap_absensi_'.$dataset['slug'].'.pdf', 'REKAP ABSENSI', $dataset['sections'], null, null, $this->pdf->teacherSigner($request), 'portrait');
    }

    public function guruSikapExcel(Request $request, KelasMapel $kelasMapel)
    {
        $dataset = $this->guruReport->sikap($kelasMapel);

        return $this->excel->table('sikap_'.$dataset['slug'].'.xlsx', 'DAFTAR SIKAP', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $dataset['semester']);
    }

    public function guruSikapPdf(Request $request, KelasMapel $kelasMapel)
    {
        $dataset = $this->guruReport->sikap($kelasMapel);

        return $this->pdf->table('sikap_'.$dataset['slug'].'.pdf', 'DAFTAR SIKAP', $dataset['context'], $dataset['headers'], $dataset['rows'], $dataset['taAktif'], $dataset['semester'], $this->pdf->teacherSigner($request));
    }

    public function guruTugasExcel(Request $request, KelasMapel $kelasMapel)
    {
        $dataset = $this->guruReport->tugas($kelasMapel);

        return $this->excel->table('tugas_'.$dataset['slug'].'.xlsx', 'DAFTAR TUGAS', $dataset['context'], $dataset['headers'], $dataset['rows']);
    }

    public function guruTugasPdf(Request $request, KelasMapel $kelasMapel)
    {
        $dataset = $this->guruReport->tugas($kelasMapel);

        return $this->pdf->table('tugas_'.$dataset['slug'].'.pdf', 'DAFTAR TUGAS', $dataset['context'], $dataset['headers'], $dataset['rows'], null, null, $this->pdf->teacherSigner($request));
    }

    public function guruUjianHasilExcel(Request $request, KelasMapel $kelasMapel, Ujian $ujian)
    {
        $dataset = $this->guruReport->ujianHasil($kelasMapel, $ujian);

        return $this->excel->table('ujian_'.$dataset['slug'].'.xlsx', 'HASIL UJIAN', $dataset['context'], $dataset['headers'], $dataset['rows']);
    }

    public function guruUjianHasilPdf(Request $request, KelasMapel $kelasMapel, Ujian $ujian)
    {
        $dataset = $this->guruReport->ujianHasil($kelasMapel, $ujian);

        return $this->pdf->table('ujian_'.$dataset['slug'].'.pdf', 'HASIL UJIAN', $dataset['context'], $dataset['headers'], $dataset['rows'], null, null, $this->pdf->teacherSigner($request));
    }

    public function guruPengumpulanTugasExcel(Request $request, KelasMapel $kelasMapel, Tugas $tugas)
    {
        $dataset = $this->guruReport->pengumpulanTugas($kelasMapel, $tugas);

        return $this->excel->table('pengumpulan_'.$dataset['slug'].'.xlsx', 'PENGUMPULAN TUGAS', $dataset['context'], $dataset['headers'], $dataset['rows']);
    }

    public function guruPengumpulanTugasPdf(Request $request, KelasMapel $kelasMapel, Tugas $tugas)
    {
        $dataset = $this->guruReport->pengumpulanTugas($kelasMapel, $tugas);

        return $this->pdf->table('pengumpulan_'.$dataset['slug'].'.pdf', 'PENGUMPULAN TUGAS', $dataset['context'], $dataset['headers'], $dataset['rows'], null, null, $this->pdf->teacherSigner($request));
    }

    private function validatedExportFilters(Request $request): array
    {
        $validated = $request->validate([
            'kelas_id' => 'required|integer|exists:kelas,id',
            'semester' => 'nullable|in:1,2',
        ]);

        return [
            'kelas_id' => (int) $validated['kelas_id'],
            'semester' => $validated['semester'] ?? Pengaturan::getValue('semester_aktif', '1'),
        ];
    }
}
