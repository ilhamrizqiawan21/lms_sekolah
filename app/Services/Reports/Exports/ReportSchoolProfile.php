<?php

namespace App\Services\Reports\Exports;

use App\Models\TahunAjaran;
use Illuminate\Support\Facades\Storage;

/**
 * Shape intentionally matches resources/views/exports/pdf/_school-header.blade.php
 * and the legacy table Excel/PDF header used across ExportController exports.
 * Kept separate from ReportMetadataService/ReportContext, which serve a
 * different (simpler) header contract used by the newer per-kelas Excel exports.
 */
final class ReportSchoolProfile
{
    public function build(?TahunAjaran $tahunAjaran, string $semester): array
    {
        $labelSemester = $semester === '1' ? 'Ganjil' : ($semester === '2' ? 'Genap' : $semester);
        $logoPath = school_setting('logo_path');

        return [
            'name' => school_setting('school_name', 'Nama Sekolah'),
            'short_name' => school_setting('school_short_name', 'LMS'),
            'address' => school_setting('address', 'Alamat sekolah belum diatur'),
            'phone' => school_setting('phone'),
            'email' => school_setting('email'),
            'website' => school_setting('website'),
            'principal_name' => school_setting('principal_name', 'Nama Kepala Sekolah'),
            'principal_nip' => school_setting('principal_nip'),
            'principal_nuptk' => school_setting('principal_nuptk'),
            'school_year' => $tahunAjaran?->tahun ?? school_setting('school_year', '-'),
            'semester' => $labelSemester,
            'logo' => $this->logoDataUri($logoPath),
        ];
    }

    private function logoDataUri(?string $path): string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return school_logo_url();
        }

        $fullPath = Storage::disk('public')->path($path);
        $contents = file_get_contents($fullPath);
        $mime = mime_content_type($fullPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
