<?php

namespace App\Exports;

use App\Models\BikeForSale;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BikesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Bikes For Sale';
    }

    public function query()
    {
        return BikeForSale::query()->with(['bikeBlueprint.brand']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Blueprint ID',
            'Blueprint Model',
            'Blueprint Year',
            'Brand Name',
            'VIN',
            'Mileage',
            'Status',
            'Currency Pricing',
            'Cost Price',
            'Sale Price',
            'Max Discount Type',
            'Max Discount Value',
            'Notes',
            'Created At',
            'Updated At',
        ];
    }

    public function map($bike): array
    {
        return [
            $bike->id,
            $bike->bike_blueprint_id,
            $bike->bikeBlueprint?->model,
            $bike->bikeBlueprint?->year,
            $bike->bikeBlueprint?->brand?->name,
            $bike->vin,
            $bike->mileage,
            $bike->status,
            $bike->currency_pricing,
            $bike->cost_price,
            $bike->sale_price,
            $bike->max_discount_type,
            $bike->max_discount_value,
            $bike->notes,
            $bike->created_at?->format('Y-m-d H:i:s'),
            $bike->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
