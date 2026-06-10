<?php

namespace App\Exports;

use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\BikeBlueprint;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BikeBlueprintsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use StylesProfessionalSheets;

    public function title(): string
    {
        return 'Bike Blueprints';
    }

    public function collection()
    {
        return BikeBlueprint::query()->with(['brand'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Brand Name',
            'Model',
            'Year',
        ];
    }

    public function map($blueprint): array
    {
        return [
            $blueprint->id,
            $blueprint->brand?->name,
            $blueprint->model,
            $blueprint->year,
        ];
    }

}
