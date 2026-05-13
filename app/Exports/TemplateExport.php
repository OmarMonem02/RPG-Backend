<?php

namespace App\Exports;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\MaintenanceServiceSector;
use App\Models\ProductCategory;
use App\Models\SparePartCategory;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Generic template export - produces a file with only the header row.
 * Used by ImportExportController::template()
 */
class TemplateExport implements WithMultipleSheets
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $columns,
        private readonly string $entity,
    ) {}

    public function sheets(): array
    {
        return array_filter([
            new TemplateImportSheet($this->sheetTitle, $this->columns),
            new TemplateInstructionsSheet($this->sheetTitle, $this->columns),
            $this->referenceSheet(),
        ]);
    }

    private function referenceSheet(): ?TemplateReferenceSheet
    {
        $references = match ($this->entity) {
            'products' => [
                ['Type', 'Name'],
                ...Brand::where('type', 'products')->orderBy('name')->get(['name'])->map(fn ($row) => ['Brand', $row->name])->all(),
                ...ProductCategory::orderBy('name')->get(['name'])->map(fn ($row) => ['Category', $row->name])->all(),
            ],
            'spare_parts' => [
                ['Type', 'Name'],
                ...Brand::where('type', 'spare_parts')->orderBy('name')->get(['name'])->map(fn ($row) => ['Brand', $row->name])->all(),
                ...SparePartCategory::orderBy('name')->get(['name'])->map(fn ($row) => ['Category', $row->name])->all(),
                ...BikeBlueprint::with('brand')->orderBy('model')->orderBy('year')->get()->map(fn ($row) => ['Bike Blueprint', "{$row->brand?->name} | {$row->model} | {$row->year}"])->all(),
            ],
            'maintenance_services' => [
                ['Type', 'Name'],
                ...MaintenanceServiceSector::orderBy('name')->get(['name'])->map(fn ($row) => ['Sector', $row->name])->all(),
            ],
            'bikes', 'bike_blueprints' => [
                ['Type', 'Name'],
                ...Brand::where('type', 'bikes')->orderBy('name')->get(['name'])->map(fn ($row) => ['Bike Brand', $row->name])->all(),
                ...BikeBlueprint::with('brand')->orderBy('model')->orderBy('year')->get()->map(fn ($row) => ['Bike Blueprint', "{$row->brand?->name} | {$row->model} | {$row->year}"])->all(),
            ],
            default => [],
        };

        return count($references) > 1 ? new TemplateReferenceSheet($references) : null;
    }
}

class TemplateImportSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $columns,
    ) {}

    public function title(): string
    {
        return 'Import';
    }

    public function array(): array
    {
        return [
            collect($this->columns)->map(fn (array $column) => $this->sampleValue($column))->all(),
        ];
    }

    public function headings(): array
    {
        return collect($this->columns)->map(fn (array $column) => $column['key'])->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF14315F']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }

    private function sampleValue(array $column): string
    {
        if (! $column['required']) {
            return '';
        }

        return match ($column['type']) {
            'integer' => '2026',
            'decimal' => '100.00',
            'boolean' => 'yes',
            'select' => $column['accepted_values'][0] ?? '',
            'reference' => 'Existing name',
            default => 'Required value',
        };
    }
}

class TemplateInstructionsSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $columns,
    ) {}

    public function title(): string
    {
        return 'Instructions';
    }

    public function headings(): array
    {
        return ['Column', 'Required', 'Type', 'Description', 'Accepted Values'];
    }

    public function array(): array
    {
        return collect($this->columns)->map(fn (array $column) => [
            $column['key'],
            $column['required'] ? 'Yes' : 'No',
            $column['type'],
            $column['description'],
            implode(', ', $column['accepted_values'] ?? []),
        ])->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

class TemplateReferenceSheet implements FromArray, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private readonly array $references) {}

    public function title(): string
    {
        return 'References';
    }

    public function array(): array
    {
        return $this->references;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
