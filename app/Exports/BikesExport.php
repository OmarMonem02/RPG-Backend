<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\BikeForSale;
use App\Support\ImportExport\ImportExportImageHelper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class BikesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
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
        return 'Bikes For Sale';
    }

    public function collection()
    {
        return BikeForSale::query()->with(['bikeBlueprint.brand', 'images'])->get();
    }

    protected function exportColumnMap(): array
    {
        return [
            'id' => 'ID',
            'brand_name' => 'Brand Name',
            'model' => 'Model',
            'year' => 'Year',
            'vin' => 'VIN',
            'mileage' => 'Mileage',
            'status' => 'Status',
            'cost_currency' => 'Cost Currency',
            'sale_currency' => 'Sale Currency',
            'cost_price' => 'Cost Price',
            'sale_price' => 'Sale Price',
            'sale_price_mode' => 'Sale Price Mode',
            'sale_margin_type' => 'Sale Margin Type',
            'sale_margin_value' => 'Sale Margin Value',
            'max_discount_type' => 'Max Discount Type',
            'max_discount_value' => 'Max Discount Value',
            'notes' => 'Notes',
            'have_commission' => 'Have Commission',
            'image_1' => 'image_1',
            'image_2' => 'image_2',
            'image_3' => 'image_3',
            'image_4' => 'image_4',
        ];
    }

    protected function mapColumn(string $key, mixed $bike): mixed
    {
        /** @var BikeForSale $bike */
        $imageHelper = new ImportExportImageHelper();
        $images = $imageHelper->exportImageColumns($bike->images);

        return match ($key) {
            'id' => $bike->id,
            'brand_name' => $bike->bikeBlueprint?->brand?->name,
            'model' => $bike->bikeBlueprint?->model,
            'year' => $bike->bikeBlueprint?->year,
            'vin' => $bike->vin,
            'mileage' => $bike->mileage,
            'status' => $bike->status,
            'cost_currency' => $bike->cost_currency,
            'sale_currency' => $bike->sale_currency,
            'cost_price' => $bike->cost_price,
            'sale_price' => $bike->sale_price,
            'sale_price_mode' => $bike->sale_price_mode,
            'sale_margin_type' => $bike->sale_margin_type,
            'sale_margin_value' => $bike->sale_margin_value,
            'max_discount_type' => $bike->max_discount_type,
            'max_discount_value' => $bike->max_discount_value,
            'notes' => $bike->notes,
            'have_commission' => $bike->have_commission ? 'Yes' : 'No',
            'image_1' => $images[0] ?? null,
            'image_2' => $images[1] ?? null,
            'image_3' => $images[2] ?? null,
            'image_4' => $images[3] ?? null,
            default => null,
        };
    }
}
