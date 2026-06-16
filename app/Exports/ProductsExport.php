<?php

namespace App\Exports;

use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\Product;
use App\Support\ImportExport\ImportExportImageHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use StylesProfessionalSheets;

    public function title(): string
    {
        return 'Products';
    }

    public function collection()
    {
        return Product::query()->with(['category', 'brand', 'bikeBlueprints.brand', 'images'])->get();
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

    public function map($product): array
    {
        $blueprints = $product->bikeBlueprints
            ->map(fn ($bp) => trim(implode(' | ', array_filter([
                $bp->brand?->name,
                $bp->model,
                $bp->year,
            ]))))
            ->filter()
            ->implode('; ');

        $imageHelper = new ImportExportImageHelper();

        return [
            $product->id,
            $product->name,
            $product->sku,
            $product->part_number,
            $product->stock_quantity,
            $product->low_stock_alarm,
            $product->category?->name,
            $product->cost_currency,
            $product->sale_currency,
            $product->cost_price,
            $product->sale_price,
            $product->sale_price_mode,
            $product->sale_margin_type,
            $product->sale_margin_value,
            $product->brand?->name,
            $product->max_discount_type,
            $product->max_discount_value,
            $product->universal ? 'Yes' : 'No',
            $product->notes,
            $blueprints,
            $product->tags ? implode('; ', $product->tags) : null,
            ...$imageHelper->exportImageColumns($product->images),
        ];
    }

}
