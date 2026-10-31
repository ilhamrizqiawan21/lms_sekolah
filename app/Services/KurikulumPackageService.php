<?php

namespace App\Services;

use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\RencanaPertemuan;
use App\Models\TujuanPembelajaran;
use ZipArchive;

class KurikulumPackageService
{
    public function create(Kurikulum $kurikulum, KelasMapel $kelasMapel): array
    {
        $kurikulum->load([
            'tahunAjaran',
            'mataPelajaran',
            'capaianPembelajaran',
            'tujuanPembelajaran',
            'rencanaPertemuan' => fn ($query) => $query
                ->where('kelas_mapel_id', $kelasMapel->id)
                ->with('tujuanPembelajaran')
                ->orderBy('tanggal_rencana')
                ->orderBy('pertemuan_ke'),
        ]);
        $kelasMapel->load(['kelas', 'mataPelajaran', 'jadwalMengajar']);

        $mapel = $kurikulum->mataPelajaran?->nama_mapel ?? $kelasMapel->mataPelajaran?->nama_mapel ?? 'Mapel';
        $kelas = $kelasMapel->kelas?->displayName() ?? 'Kelas';
        $semester = (string) $kurikulum->semester;
        $semesterLabel = $semester === '2' ? 'Genap' : 'Ganjil';
        $documents = $this->documents($kurikulum, $kelasMapel, $semesterLabel);
        $packageName = $this->safeName('Paket Administrasi '.$mapel.' '.$kelas.' '.($kurikulum->tahunAjaran?->tahun ?? ''));
        $filename = $packageName.'.zip';
        $path = tempnam(sys_get_temp_dir(), 'lms-paket-');

        $zip = new ZipArchive;
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Paket administrasi tidak dapat dibuat.');
        }

        try {
            foreach ($documents as $document) {
                $zip->addFromString($document['filename'], $this->docx($document['title'], $document['lines']));
            }
            $zip->close();
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'filename' => $filename, 'documents' => count($documents)];
    }

    private function documents(Kurikulum $kurikulum, KelasMapel $kelasMapel, string $semesterLabel): array
    {
        $subject = $kurikulum->mataPelajaran?->nama_mapel ?? $kelasMapel->mataPelajaran?->nama_mapel ?? '-';
        $semester = 'Semester '.$semesterLabel;
        $plans = $kurikulum->rencanaPertemuan;
        $tpLines = $kurikulum->tujuanPembelajaran->map(fn (TujuanPembelajaran $item) => implode(' | ', array_filter([
            $item->kode,
            $item->tujuan,
            $item->materi_pokok ? 'Materi: '.$item->materi_pokok : null,
            'Alokasi JP: '.($item->alokasi_jp ?? 0),
        ])))->all();
        $cpLines = $kurikulum->capaianPembelajaran->map(fn ($item) => implode(' | ', array_filter([$item->kode, $item->elemen, $item->deskripsi])))->all();
        $planLines = $plans->map(fn (RencanaPertemuan $item) => implode(' | ', array_filter([
            $item->tanggal_rencana?->format('d/m/Y'),
            'Pertemuan '.$item->pertemuan_ke,
            $item->tujuanPembelajaran?->kode,
            $item->tujuanPembelajaran?->tujuan,
            $item->jp.' JP',
            ucfirst($item->status),
        ])))->all();
        $scheduleLines = $kelasMapel->jadwalMengajar->map(fn ($item) => 'Hari ke-'.$item->hari.' | Jam ke-'.$item->pelajaran_ke)->all();

        return [
            $this->document('01 Capaian Pembelajaran', $subject, ['Kurikulum: '.$kurikulum->nama, ...$this->orEmpty($cpLines)]),
            $this->document('02 Alur Tujuan Pembelajaran', $subject, [$semester, ...$this->orEmpty($tpLines)]),
            $this->document('03 Alokasi Waktu', $subject, [$semester, 'Total alokasi JP: '.$kurikulum->tujuanPembelajaran->sum('alokasi_jp'), ...$this->orEmpty($tpLines)]),
            $this->document('04 Program Tahunan', $subject, ['Tahun Ajaran: '.($kurikulum->tahunAjaran?->tahun ?? '-'), ...$this->orEmpty($tpLines)]),
            $this->document('05 Program Semester - '.$semesterLabel, $subject, [$semester, ...$this->orEmpty($planLines)]),
            $this->document('07 Jadwal Pertemuan - '.$semesterLabel, $subject, [$semester, 'Jadwal mengajar:', ...$this->orEmpty($scheduleLines), 'Rencana pertemuan:', ...$this->orEmpty($planLines)]),
            $this->document('09 Ketuntasan - '.$semesterLabel, $subject, [$semester, ...$kurikulum->tujuanPembelajaran->map(fn (TujuanPembelajaran $item) => implode(' | ', array_filter([$item->kode, $item->tujuan, $item->indikator_ketercapaian ? 'Indikator: '.$item->indikator_ketercapaian : null])))->all()]),
        ];
    }

    private function document(string $title, string $subject, array $lines): array
    {
        return [
            'title' => $title,
            'filename' => $this->safeName($title.' '.$subject).'.docx',
            'lines' => ['Mata Pelajaran: '.$subject, ...$lines],
        ];
    }

    private function orEmpty(array $lines): array
    {
        return $lines !== [] ? $lines : ['Data belum diisi.'];
    }

    private function safeName(string $value): string
    {
        $value = preg_replace('~[\\/:*?"<>|]+~u', ' ', $value) ?? 'Dokumen';
        $value = preg_replace('/\s+/u', ' ', trim($value, " .\t\n\r\0\x0B")) ?? 'Dokumen';

        return mb_substr($value !== '' ? $value : 'Dokumen', 0, 120);
    }

    private function docx(string $title, array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lms-docx-');
        if ($path === false) {
            throw new \RuntimeException('Dokumen DOCX tidak dapat dibuat.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new \RuntimeException('Arsip DOCX tidak dapat dibuka.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('word/document.xml', $this->documentXml($title, $lines));
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRels());
        $zip->close();
        $contents = file_get_contents($path);
        @unlink($path);

        if ($contents === false) {
            throw new \RuntimeException('Isi DOCX tidak dapat dibaca.');
        }

        return $contents;
    }

    private function documentXml(string $title, array $lines): string
    {
        $paragraphs = [$this->paragraph($title, true)];
        foreach ($lines as $line) {
            $paragraphs[] = $this->paragraph((string) $line);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .implode('', $paragraphs)
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr></w:body></w:document>';
    }

    private function paragraph(string $text, bool $bold = false): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $runProperties = $bold ? '<w:rPr><w:b/></w:rPr>' : '';

        return '<w:p><w:r>'.$runProperties.'<w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function documentRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>';
    }
}
