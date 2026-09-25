<?php

namespace App\Services\Reports\Exports;

use App\Models\Pengaturan;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

final class TablePdfRenderer
{
    public function __construct(private readonly ReportSchoolProfile $schoolProfile) {}

    public function table(string $filename, string $title, string $context, array $headers, array $rows, ?TahunAjaran $tahunAjaran = null, ?string $semester = null, ?array $signer = null, ?string $orientation = null)
    {
        return $this->multiTable($filename, $title, [
            ['context' => $context, 'headers' => $headers, 'rows' => $rows],
        ], $tahunAjaran, $semester, $signer, $orientation);
    }

    public function multiTable(string $filename, string $title, array $sections, ?TahunAjaran $tahunAjaran = null, ?string $semester = null, ?array $signer = null, ?string $orientation = null)
    {
        $reportSchool = $this->schoolProfile->build($tahunAjaran ?? TahunAjaran::getAktif(), $semester ?? Pengaturan::getValue('semester_aktif', '1'));
        $signer ??= $this->principalSigner($reportSchool);
        $maxHeaders = max(1, collect($sections)->max(fn (array $section) => count($section['headers'])));
        $pdf = Pdf::loadView('exports.pdf.table', compact('title', 'sections', 'reportSchool', 'signer'));
        $pdf->setPaper('A4', $orientation ?? ($maxHeaders > 8 ? 'landscape' : 'portrait'));

        return $pdf->download($filename);
    }

    public function teacherSigner(Request $request): array
    {
        $user = $request->user();

        return [
            'role' => 'Guru Mata Pelajaran',
            'name' => $user?->nama_lengkap ?? $user?->username ?? '-',
            'id_label' => filled($user?->nip_nis) ? 'NIP' : null,
            'id_value' => $user?->nip_nis,
        ];
    }

    public function principalSigner(array $reportSchool): array
    {
        return [
            'role' => 'Kepala Sekolah',
            'name' => $reportSchool['principal_name'] ?? '-',
            'id_label' => ($reportSchool['principal_nip'] ?? null) ? 'NIP' : (($reportSchool['principal_nuptk'] ?? null) ? 'NUPTK' : null),
            'id_value' => $reportSchool['principal_nip'] ?: ($reportSchool['principal_nuptk'] ?? null),
        ];
    }
}
