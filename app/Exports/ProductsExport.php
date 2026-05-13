<?php

namespace App\Exports;

use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\Product;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use StylesProfessionalSheets;

    public function title(): string
    {
        return 'Products';
    }

    public function query()
    {
        return Product::query()->with(['category', 'brand']);
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
            'Currency Pricing',
            'Cost Price',
            'Sale Price',
            'Brand Name',
            'Max Discount Type',
            'Max Discount Value',
            'Universal',
            'Notes',
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->sku,
            $product->part_number,
            $product->stock_quantity,
            $product->low_stock_alarm,
            $product->category?->name,
            $product->currency_pricing,
            $product->cost_price,
            $product->sale_price,
            $product->brand?->name,
            $product->max_discount_type,
            $product->max_discount_value,
            $product->universal ? 'Yes' : 'No',
            $product->notes,
        ];
    }

}
