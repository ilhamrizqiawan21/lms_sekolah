<?php

namespace App\Services\Reports\Exports;

use App\Models\Pengaturan;
use App\Models\TahunAjaran;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Renders the "generic table" Excel exports (single or multi-section) shared by
 * Guru/Kepsek/Admin report exports. Kept separate from ExcelReportWriter, whose
 * simpler header layout (no address/principal row) is a different, already-agreed
 * contract used only by the newer per-kelas exports (Nilai/Absensi/Tugas single-kelas
 * ExportService classes). Merging the two would change rendered output for every
 * export still using this writer, so they are intentionally kept apart for now.
 */
final class LegacyTableExcelWriter
{
    private ?array $stylesCache = null;

    public function __construct(private readonly ReportSchoolProfile $schoolProfile) {}

    public function table(string $filename, string $title, string $context, array $headers, array $rows, ?TahunAjaran $tahunAjaran = null, ?string $semester = null)
    {
        return $this->multiTable($filename, $title, [
            ['context' => $context, 'headers' => $headers, 'rows' => $rows],
        ], $tahunAjaran, $semester);
    }

    public function multiTable(string $filename, string $title, array $sections, ?TahunAjaran $tahunAjaran = null, ?string $semester = null)
    {
        $writer = new Writer;
        $filePath = $this->temporaryExcelPath('export_');
        $writer->openToFile($filePath);
        $maxColumns = max(1, collect($sections)->max(fn (array $section) => count($section['headers'])));
        $this->prepareWorksheet($writer, $maxColumns);

        $reportSchool = $this->schoolProfile->build($tahunAjaran ?? TahunAjaran::getAktif(), $semester ?? Pengaturan::getValue('semester_aktif', '1'));
        foreach ($sections as $index => $section) {
            $this->writeExcelReportHeader($writer, $title, $reportSchool, $section['context']);
            $this->writeExcelTableHeader($writer, $section['headers']);
            foreach ($section['rows'] as $rowIndex => $row) {
                $this->writeExcelDataRow($writer, $row, $rowIndex);
            }
            if ($index < count($sections) - 1) {
                $writer->addRow(Row::fromValues([]));
            }
        }
        $writer->close();

        return response()->download($filePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Renders a simpler, single-block Excel export: school name, title, a
     * handful of meta rows, then the data table. Used by exports that don't
     * carry the full school-profile header (address/context/tahun ajaran/
     * kepala sekolah) built by table()/multiTable(), e.g. Admin user and log
     * login exports.
     *
     * @param  array<int, int>  $columnWidths  Column index (1-based) => width.
     * @param  array<int, array{0: array<int, mixed>, 1: string, 2: int}>  $headerRows  Tuples of [values, style key, row height].
     */
    public function simpleTable(string $filename, array $columnWidths, array $headerRows, array $headers, iterable $rows, string $tempPrefix = 'export_')
    {
        $writer = new Writer;
        $filePath = $this->temporaryExcelPath($tempPrefix);
        $writer->openToFile($filePath);

        $sheet = $writer->getCurrentSheet();
        foreach ($columnWidths as $column => $width) {
            $sheet->setColumnWidth($width, $column);
        }

        $styles = $this->excelStyles();
        foreach ($headerRows as [$values, $styleKey, $height]) {
            $writer->addRow(Row::fromValuesWithStyle($values, $styles[$styleKey], $height));
        }
        $writer->addRow(Row::fromValues([]));

        $this->writeExcelTableHeader($writer, $headers);
        foreach ($rows as $index => $row) {
            $this->writeExcelDataRow($writer, $row, $index);
        }

        $writer->close();

        return response()->download($filePath, $filename)->deleteFileAfterSend(true);
    }

    private function excelReportHeader(string $title, array $school, string $context): array
    {
        $principalId = $school['principal_nip'] ?: $school['principal_nuptk'];

        return [
            [$school['name']],
            [$school['address']],
            [$title],
            [$context],
            ['Tahun Ajaran', $school['school_year'], 'Semester', $school['semester']],
            ['Kepala Sekolah', $school['principal_name'], 'NIP/NUPTK', $principalId ?: '-'],
            [],
        ];
    }

    private function temporaryExcelPath(string $prefix): string
    {
        return tempnam(sys_get_temp_dir(), $prefix);
    }

    private function prepareWorksheet(Writer $writer, int $columnCount): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setColumnWidth(6, 1);

        if ($columnCount >= 2) {
            $sheet->setColumnWidth(15, 2);
        }

        if ($columnCount >= 3) {
            $sheet->setColumnWidth(28, 3);
        }

        if ($columnCount > 3) {
            $sheet->setColumnWidthForRange(16, 4, $columnCount);
        }
    }

    private function writeExcelReportHeader(Writer $writer, string $title, array $school, string $context): void
    {
        $styles = $this->excelStyles();

        $rows = $this->excelReportHeader($title, $school, $context);
        $writer->addRow(Row::fromValuesWithStyle($rows[0], $styles['school'], 24));
        $writer->addRow(Row::fromValuesWithStyle($rows[1], $styles['meta'], 18));
        $writer->addRow(Row::fromValuesWithStyle($rows[2], $styles['title'], 24));
        $writer->addRow(Row::fromValuesWithStyle($rows[3], $styles['context'], 20));
        $writer->addRow(Row::fromValuesWithStyle($rows[4], $styles['meta'], 18));
        $writer->addRow(Row::fromValuesWithStyle($rows[5], $styles['meta'], 18));
        $writer->addRow(Row::fromValues([]));
    }

    private function writeExcelTableHeader(Writer $writer, array $headers): void
    {
        $writer->addRow(Row::fromValuesWithStyle($headers, $this->excelStyles()['tableHeader'], 24));
    }

    private function writeExcelDataRow(Writer $writer, array $row, int $index): void
    {
        $style = $index % 2 === 0 ? $this->excelStyles()['row'] : $this->excelStyles()['alternateRow'];
        $writer->addRow(Row::fromValuesWithStyle($row, $style, 20));
    }

    private function excelStyles(): array
    {
        if ($this->stylesCache !== null) {
            return $this->stylesCache;
        }

        $border = new Border(
            new BorderPart(BorderName::TOP, 'CBD5E1', BorderWidth::THIN),
            new BorderPart(BorderName::RIGHT, 'CBD5E1', BorderWidth::THIN),
            new BorderPart(BorderName::BOTTOM, 'CBD5E1', BorderWidth::THIN),
            new BorderPart(BorderName::LEFT, 'CBD5E1', BorderWidth::THIN),
        );

        $base = (new Style)
            ->withFontName('Arial')
            ->withFontSize(10)
            ->withShouldWrapText(true)
            ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);

        return $this->stylesCache = [
            'school' => $base
                ->withFontBold(true)
                ->withFontSize(14)
                ->withFontColor('0F172A')
                ->withCellAlignment(CellAlignment::CENTER),
            'title' => $base
                ->withFontBold(true)
                ->withFontSize(13)
                ->withFontColor(Color::WHITE)
                ->withBackgroundColor('1D4ED8')
                ->withCellAlignment(CellAlignment::CENTER),
            'context' => $base
                ->withFontBold(true)
                ->withFontColor('1E3A8A')
                ->withBackgroundColor('DBEAFE')
                ->withCellAlignment(CellAlignment::CENTER),
            'meta' => $base
                ->withFontColor('475569')
                ->withBackgroundColor('F8FAFC'),
            'tableHeader' => $base
                ->withFontBold(true)
                ->withFontColor(Color::WHITE)
                ->withBackgroundColor('334155')
                ->withCellAlignment(CellAlignment::CENTER)
                ->withBorder($border),
            'row' => $base
                ->withBackgroundColor(Color::WHITE)
                ->withBorder($border),
            'alternateRow' => $base
                ->withBackgroundColor('F8FAFC')
                ->withBorder($border),
        ];
    }
}
