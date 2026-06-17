<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\SparePart;
use App\Support\ImportExport\BikeBlueprintReferenceFormatter;
use App\Support\ImportExport\ImportExportImageHelper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class SparePartsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
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
        return 'Spare Parts';
    }

    public function collection()
    {
        return SparePart::query()->with(['category', 'brand', 'bikeBlueprints.brand', 'images'])->get();
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

    protected function mapColumn(string $key, mixed $part): mixed
    {
        /** @var SparePart $part */
        $imageHelper = new ImportExportImageHelper();
        $images = $imageHelper->exportImageColumns($part->images);

        return match ($key) {
            'id' => $part->id,
            'name' => $part->name,
            'sku' => $part->sku,
            'part_number' => $part->part_number,
            'size' => $part->size,
            'color' => $part->color,
            'item_status' => $part->item_status instanceof \BackedEnum ? $part->item_status->value : $part->item_status,
            'stock_quantity' => $part->stock_quantity,
            'low_stock_alarm' => $part->low_stock_alarm,
            'category_name' => $part->category?->name,
            'cost_currency' => $part->cost_currency,
            'sale_currency' => $part->sale_currency,
            'cost_price' => $part->cost_price,
            'sale_price' => $part->sale_price,
            'sale_price_mode' => $part->sale_price_mode,
            'sale_margin_type' => $part->sale_margin_type,
            'sale_margin_value' => $part->sale_margin_value,
            'brand_name' => $part->brand?->name,
            'max_discount_type' => $part->max_discount_type,
            'max_discount_value' => $part->max_discount_value,
            'universal' => $part->universal ? 'Yes' : 'No',
            'notes' => $part->notes,
            'bike_blueprints' => (new BikeBlueprintReferenceFormatter())->formatCollection($part->bikeBlueprints),
            'tags' => $part->tags ? implode('; ', $part->tags) : null,
            'image_1' => $images[0] ?? null,
            'image_2' => $images[1] ?? null,
            'image_3' => $images[2] ?? null,
            'image_4' => $images[3] ?? null,
            default => null,
        };
    }
}
