<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\Product;
use App\Support\ImportExport\BikeBlueprintReferenceFormatter;
use App\Support\ImportExport\ImportExportImageHelper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use HasOrderedExportColumns;
    use StylesProfessionalSheets;

    /**
     * @param  list<string>|null  $columnKeys
     */
    public function __construct(?array $columnKeys = null)
    {
        $this->columnKeys = $columnKeys;
    }

    public function title(): string
    {
        return 'Products';
    }

    public function collection()
    {
        return Product::query()->with(['category', 'brand', 'bikeBlueprints.brand', 'images'])->get();
    }

    protected function exportColumnMap(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'sku' => 'SKU',
            'part_number' => 'Part Number',
            'size' => 'Size',
            'color' => 'Color',
            'item_status' => 'Item Status',
            'stock_quantity' => 'Stock Quantity',
            'low_stock_alarm' => 'Low Stock Alarm',
            'category_name' => 'Category Name',
            'cost_currency' => 'Cost Currency',
            'sale_currency' => 'Sale Currency',
            'cost_price' => 'Cost Price',
            'sale_price' => 'Sale Price',
            'sale_price_mode' => 'Sale Price Mode',
            'sale_margin_type' => 'Sale Margin Type',
            'sale_margin_value' => 'Sale Margin Value',
            'brand_name' => 'Brand Name',
            'max_discount_type' => 'Max Discount Type',
            'max_discount_value' => 'Max Discount Value',
            'universal' => 'Universal',
            'notes' => 'Notes',
            'bike_blueprints' => 'bike_blueprints',
            'tags' => 'tags',
            'image_1' => 'image_1',
            'image_2' => 'image_2',
            'image_3' => 'image_3',
            'image_4' => 'image_4',
        ];
    }

    protected function mapColumn(string $key, mixed $product): mixed
    {
        /** @var Product $product */
        $imageHelper = new ImportExportImageHelper();
        $images = $imageHelper->exportImageColumns($product->images);

        return match ($key) {
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'part_number' => $product->part_number,
            'size' => $product->size,
            'color' => $product->color,
            'item_status' => $product->item_status instanceof \BackedEnum ? $product->item_status->value : $product->item_status,
            'stock_quantity' => $product->stock_quantity,
            'low_stock_alarm' => $product->low_stock_alarm,
            'category_name' => $product->category?->name,
            'cost_currency' => $product->cost_currency,
            'sale_currency' => $product->sale_currency,
            'cost_price' => $product->cost_price,
            'sale_price' => $product->sale_price,
            'sale_price_mode' => $product->sale_price_mode,
            'sale_margin_type' => $product->sale_margin_type,
            'sale_margin_value' => $product->sale_margin_value,
            'brand_name' => $product->brand?->name,
            'max_discount_type' => $product->max_discount_type,
            'max_discount_value' => $product->max_discount_value,
            'universal' => $product->universal ? 'Yes' : 'No',
            'notes' => $product->notes,
            'bike_blueprints' => (new BikeBlueprintReferenceFormatter())->formatCollection($product->bikeBlueprints),
            'tags' => $product->tags ? implode('; ', $product->tags) : null,
            'image_1' => $images[0] ?? null,
            'image_2' => $images[1] ?? null,
            'image_3' => $images[2] ?? null,
            'image_4' => $images[3] ?? null,
            default => null,
        };
    }
}
