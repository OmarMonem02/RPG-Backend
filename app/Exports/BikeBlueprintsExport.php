<?php

namespace App\Exports;

use App\Models\BikeBlueprint;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BikeBlueprintsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Bike Blueprints';
    }

    public function query()
    {
        return BikeBlueprint::query()->with(['brand']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Brand ID',
            'Brand Name',
            'Model',
            'Year',
            'Created At',
            'Updated At',
        ];
    }

    public function map($blueprint): array
    {
        return [
            $blueprint->id,
            $blueprint->brand_id,
            $blueprint->brand?->name,
            $blueprint->model,
            $blueprint->year,
            $blueprint->created_at?->format('Y-m-d H:i:s'),
            $blueprint->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
