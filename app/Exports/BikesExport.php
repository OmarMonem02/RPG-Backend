<?php

namespace App\Exports;

use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\BikeForSale;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BikesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use StylesProfessionalSheets;

    public function title(): string
    {
        return 'Bikes For Sale';
    }

    public function collection()
    {
        return BikeForSale::query()->with(['bikeBlueprint.brand'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Brand Name',
            'Model',
            'Year',
            'VIN',
            'Mileage',
            'Status',
            'Currency Pricing',
            'Cost Currency',
            'Sale Currency',
            'Cost Price',
            'Sale Price',
            'Sale Price Mode',
            'Sale Margin Type',
            'Sale Margin Value',
            'Max Discount Type',
            'Max Discount Value',
            'Notes',
            'image',
        ];
    }

    public function map($bike): array
    {
        return [
            $bike->id,
            $bike->bikeBlueprint?->brand?->name,
            $bike->bikeBlueprint?->model,
            $bike->bikeBlueprint?->year,
            $bike->vin,
            $bike->mileage,
            $bike->status,
            $bike->currency_pricing,
            $bike->cost_currency,
            $bike->sale_currency,
            $bike->cost_price,
            $bike->sale_price,
            $bike->sale_price_mode,
            $bike->sale_margin_type,
            $bike->sale_margin_value,
            $bike->max_discount_type,
            $bike->max_discount_value,
            $bike->notes,
            $bike->image,
        ];
    }

}
