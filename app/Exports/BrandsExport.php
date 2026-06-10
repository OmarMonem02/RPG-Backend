<?php

namespace App\Exports;

use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\Brand;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BrandsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use StylesProfessionalSheets;

    public function title(): string
    {
        return 'Brands';
    }

    public function collection()
    {
        return Brand::query()->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Types',
        ];
    }

    public function map($brand): array
    {
        return [
            $brand->id,
            $brand->name,
            implode(', ', $brand->types ?? []),
        ];
    }

}
