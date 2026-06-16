<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class StocktakeDiscrepancyExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use HasOrderedExportColumns;
    use StylesProfessionalSheets;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>|null  $columnKeys
     */
    public function __construct(
        private readonly array $rows,
        ?array $columnKeys = null,
    ) {
        $this->columnKeys = $columnKeys;
    }

    public function title(): string
    {
        return 'Discrepancies';
    }

    protected function exportColumnMap(): array
    {
        return [
            'type' => 'Type',
            'name' => 'Name',
            'sku' => 'SKU',
            'part_number' => 'Part Number',
            'system_quantity' => 'System Qty',
            'counted_quantity' => 'Counted Qty',
            'variance' => 'Variance',
            'status' => 'Status',
        ];
    }

    protected function mapColumn(string $key, mixed $row): mixed
    {
        /** @var array<string, mixed> $row */
        return match ($key) {
            'type' => $row['type_label'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'part_number' => $row['part_number'],
            'system_quantity' => $row['system_quantity'],
            'counted_quantity' => $row['counted_quantity'],
            'variance' => $row['variance'],
            'status' => $row['status'],
            default => null,
        };
    }

    public function headings(): array
    {
        $map = $this->exportColumnMap();

        return array_map(
            fn (string $key) => $map[$key],
            $this->resolvedColumnKeys(),
        );
    }

    public function array(): array
    {
        return array_map(
            fn (array $row) => array_map(
                fn (string $key) => $this->mapColumn($key, $row),
                $this->resolvedColumnKeys(),
            ),
            $this->rows,
        );
    }
}
