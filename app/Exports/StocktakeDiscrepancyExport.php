<?php

namespace App\Exports;

use App\Exports\Concerns\StylesProfessionalSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class StocktakeDiscrepancyExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use StylesProfessionalSheets;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(private readonly array $rows) {}

    public function title(): string
    {
        return 'Discrepancies';
    }

    public function headings(): array
    {
        return [
            'Type',
            'Name',
            'SKU',
            'Part Number',
            'System Qty',
            'Counted Qty',
            'Variance',
            'Status',
        ];
    }

    public function array(): array
    {
        return array_map(static fn (array $row): array => [
            $row['type_label'],
            $row['name'],
            $row['sku'],
            $row['part_number'],
            $row['system_quantity'],
            $row['counted_quantity'],
            $row['variance'],
            $row['status'],
        ], $this->rows);
    }
}
