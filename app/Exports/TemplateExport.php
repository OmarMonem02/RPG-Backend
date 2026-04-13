<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Generic template export - produces a file with only the header row.
 * Used by ImportExportController::template()
 */
class TemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array  $columns,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /** Return one empty example row so the file isn't completely blank */
    public function array(): array
    {
        return [
            array_fill(0, count($this->columns), ''),
        ];
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF4F81BD']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
