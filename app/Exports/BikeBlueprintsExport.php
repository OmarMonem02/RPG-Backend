<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\BikeBlueprint;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class BikeBlueprintsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use HasOrderedExportColumns;
    use StylesProfessionalSheets;

    /**
     * @param  list<string>|null  $columnKeys
     */
    public function __construct(?array $columnKeys = null)
    {
        $this->columnKeys = $columnKeys;
    }

    public function title(): string
    {
        return 'Bike Blueprints';
    }

    public function collection()
    {
        return BikeBlueprint::query()->with(['brand'])->get();
    }

    protected function exportColumnMap(): array
    {
        return [
            'id' => 'ID',
            'brand_name' => 'Brand Name',
            'model' => 'Model',
            'year' => 'Year',
        ];
    }

    protected function mapColumn(string $key, mixed $blueprint): mixed
    {
        /** @var BikeBlueprint $blueprint */
        return match ($key) {
            'id' => $blueprint->id,
            'brand_name' => $blueprint->brand?->name,
            'model' => $blueprint->model,
            'year' => $blueprint->year,
            default => null,
        };
    }
}
