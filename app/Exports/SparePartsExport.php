<?php

namespace App\Exports;

use App\Models\SparePart;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SparePartsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Spare Parts';
    }

    public function query()
    {
        return SparePart::query()->with(['category', 'brand']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'SKU',
            'Part Number',
            'Stock Quantity',
            'Low Stock Alarm',
            'Category ID',
            'Category Name',
            'Currency Pricing',
            'Cost Price',
            'Sale Price',
            'Brand ID',
            'Brand Name',
            'Max Discount Type',
            'Max Discount Value',
            'Universal',
            'Notes',
            'Created At',
            'Updated At',
        ];
    }

    public function map($part): array
    {
        return [
            $part->id,
            $part->name,
            $part->sku,
            $part->part_number,
            $part->stock_quantity,
            $part->low_stock_alarm,
            $part->spare_parts_category_id,
            $part->category?->name,
            $part->currency_pricing,
            $part->cost_price,
            $part->sale_price,
            $part->brand_id,
            $part->brand?->name,
            $part->max_discount_type,
            $part->max_discount_value,
            $part->universal ? 'Yes' : 'No',
            $part->notes,
            $part->created_at?->format('Y-m-d H:i:s'),
            $part->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
