<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
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

    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->sku,
            $product->part_number,
            $product->stock_quantity,
            $product->low_stock_alarm,
            $product->products_category_id,
            $product->category?->name,
            $product->currency_pricing,
            $product->cost_price,
            $product->sale_price,
            $product->brand_id,
            $product->brand?->name,
            $product->max_discount_type,
            $product->max_discount_value,
            $product->universal ? 'Yes' : 'No',
            $product->notes,
            $product->created_at?->format('Y-m-d H:i:s'),
            $product->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
