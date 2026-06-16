<?php

namespace App\Exports;

use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\SparePart;
use App\Support\ImportExport\ImportExportImageHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class SparePartsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use StylesProfessionalSheets;

    public function title(): string
    {
        return 'Spare Parts';
    }

    public function collection()
    {
        return SparePart::query()->with(['category', 'brand', 'bikeBlueprints.brand', 'images'])->get();
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
            'Category Name',
            'Cost Currency',
            'Sale Currency',
            'Cost Price',
            'Sale Price',
            'Sale Price Mode',
            'Sale Margin Type',
            'Sale Margin Value',
            'Brand Name',
            'Max Discount Type',
            'Max Discount Value',
            'Universal',
            'Notes',
            'bike_blueprints',
            'tags',
            'image_1',
            'image_2',
            'image_3',
            'image_4',
        ];
    }

    public function map($part): array
    {
        $blueprints = $part->bikeBlueprints
            ->map(fn ($bp) => trim(implode(' | ', array_filter([
                $bp->brand?->name,
                $bp->model,
                $bp->year,
            ]))))
            ->filter()
            ->implode('; ');

        $imageHelper = new ImportExportImageHelper();

        return [
            $part->id,
            $part->name,
            $part->sku,
            $part->part_number,
            $part->stock_quantity,
            $part->low_stock_alarm,
            $part->category?->name,
            $part->cost_currency,
            $part->sale_currency,
            $part->cost_price,
            $part->sale_price,
            $part->sale_price_mode,
            $part->sale_margin_type,
            $part->sale_margin_value,
            $part->brand?->name,
            $part->max_discount_type,
            $part->max_discount_value,
            $part->universal ? 'Yes' : 'No',
            $part->notes,
            $blueprints,
            $part->tags ? implode('; ', $part->tags) : null,
            ...$imageHelper->exportImageColumns($part->images),
        ];
    }

}
