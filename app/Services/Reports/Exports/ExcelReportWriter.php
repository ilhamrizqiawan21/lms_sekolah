<?php

namespace App\Services\Reports\Exports;

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

final class ExcelReportWriter
{
    public function open(string $prefix, int $columnCount): array
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        $writer = new Writer;
        $writer->openToFile($path);
        $this->prepareWorksheet($writer, $columnCount);

        return [$writer, $path];
    }

    public function header(Writer $writer, string $title, array $school, string $subtitle): void
    {
        $styles = $this->styles();
        $writer->addRow(Row::fromValuesWithStyle([$school['name'] ?? 'Nama Sekolah'], $styles['school'], 24));
        $writer->addRow(Row::fromValuesWithStyle([$title], $styles['title'], 24));
        $writer->addRow(Row::fromValuesWithStyle([
            'Tahun Ajaran', $school['academic_year'] ?? '-',
            'Semester', $school['semester_label'] ?? '-',
        ], $styles['meta'], 18));
        $writer->addRow(Row::fromValuesWithStyle([$subtitle], $styles['meta'], 18));
        $writer->addRow(Row::fromValues([]));
    }

    public function tableHeader(Writer $writer, array $headers): void
    {
        $writer->addRow(Row::fromValuesWithStyle($headers, $this->styles()['tableHeader'], 24));
    }

    public function dataRow(Writer $writer, array $values, int $index): void
    {
        $styles = $this->styles();
        $writer->addRow(Row::fromValuesWithStyle(
            $values,
            $index % 2 === 0 ? $styles['row'] : $styles['alternateRow'],
            20
        ));
    }

    public function close(Writer $writer, string $path, string $filename): array
    {
        $writer->close();

        return [$path, $filename];
    }

    private function prepareWorksheet(Writer $writer, int $columnCount): void
    {
        $writer->getCurrentSheet()->setName('Laporan');
        for ($i = 1; $i <= $columnCount; $i++) {
            $writer->getCurrentSheet()->setColumnWidth(18, $i);
        }
    }

    private function styles(): array
    {
        $border = new Border(
            new BorderPart(BorderName::TOP, Color::GRAY, BorderWidth::THIN),
            new BorderPart(BorderName::BOTTOM, Color::GRAY, BorderWidth::THIN),
            new BorderPart(BorderName::LEFT, Color::GRAY, BorderWidth::THIN),
            new BorderPart(BorderName::RIGHT, Color::GRAY, BorderWidth::THIN),
        );

        return [
            'school' => (new Style)->setFontBold()->setFontSize(16)->setHorizontalAlignment(CellAlignment::CENTER)->setVerticalAlignment(CellVerticalAlignment::CENTER),
            'title' => (new Style)->setFontBold()->setFontSize(13)->setHorizontalAlignment(CellAlignment::CENTER)->setVerticalAlignment(CellVerticalAlignment::CENTER),
            'meta' => (new Style)->setFontSize(10)->setBorder($border),
            'tableHeader' => (new Style)->setFontBold()->setFontSize(10)->setBorder($border)->setHorizontalAlignment(CellAlignment::CENTER)->setVerticalAlignment(CellVerticalAlignment::CENTER),
            'row' => (new Style)->setFontSize(10)->setBorder($border),
            'alternateRow' => (new Style)->setFontSize(10)->setBorder($border),
        ];
    }
}
