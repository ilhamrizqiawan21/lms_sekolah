<?php

namespace App\Services\Reports;

use App\Models\Pengaturan;
use App\Models\TahunAjaran;

final class ReportMetadataService
{
    public function context(?string $semester = null): ReportContext
    {
        $settings = $this->loadSettings();
        $tahunAjaran = TahunAjaran::getAktif();
        $semester = $semester ?: (string) ($settings['semester_aktif'] ?? '1');

        return new ReportContext(
            tahunAjaran: $tahunAjaran,
            semester: $semester,
            school: $this->school($settings),
        );
    }

    /** Shape intentionally matches resources/views/exports/pdf/_school-header.blade.php. */
    public function school(?array $settings = null): array
    {
        $settings ??= $this->loadSettings();

        return [
            'name' => $this->firstSetting($settings, ['nama_sekolah', 'school_name', 'nama_instansi'], 'Sekolah'),
            'address' => $this->firstSetting($settings, ['alamat_sekolah', 'school_address', 'alamat'], ''),
            'phone' => $this->firstSetting($settings, ['telepon_sekolah', 'school_phone', 'telepon'], ''),
            'email' => $this->firstSetting($settings, ['email_sekolah', 'school_email', 'email'], ''),
            'website' => $this->firstSetting($settings, ['website_sekolah', 'school_website', 'website'], ''),
            'npsn' => $this->firstSetting($settings, ['npsn'], ''),
            'logo' => $this->logoPath($settings),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function loadSettings(): array
    {
        return Pengaturan::query()->pluck('value', 'key')->all();
    }

    /**
     * @param  array<string, string|null>  $settings
     */
    private function firstSetting(array $settings, array $keys, string $default): string
    {
        foreach ($keys as $key) {
            $value = $settings[$key] ?? null;
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return $default;
    }

    /**
     * @param  array<string, string|null>  $settings
     */
    private function logoPath(array $settings): ?string
    {
        $configured = $this->firstSetting($settings, ['logo_sekolah', 'school_logo', 'logo'], '');
        if ($configured === '') {
            return null;
        }
        if (is_file(public_path($configured))) {
            return public_path($configured);
        }
        if (is_file(storage_path('app/public/'.$configured))) {
            return storage_path('app/public/'.$configured);
        }

        return null;
    }
}
