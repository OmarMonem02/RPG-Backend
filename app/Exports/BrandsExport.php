<?php

namespace App\Exports;

use App\Models\Brand;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BrandsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Brands';
    }

    public function query()
    {
        return Brand::query();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Type',
            'Created At',
            'Updated At',
        ];
    }

    public function map($brand): array
    {
        return [
            $brand->id,
            $brand->name,
            $brand->type,
            $brand->created_at?->format('Y-m-d H:i:s'),
            $brand->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
