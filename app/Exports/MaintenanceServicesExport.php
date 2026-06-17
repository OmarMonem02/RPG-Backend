<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\MaintenanceService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaintenanceServicesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
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
        return 'Maintenance Services';
    }

    public function collection()
    {
        return MaintenanceService::query()->with(['sector'])->get();
    }

    protected function exportColumnMap(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'sale_currency' => 'Sale Currency',
            'service_price' => 'Service Price',
            'max_discount_type' => 'Max Discount Type',
            'max_discount_value' => 'Max Discount Value',
            'sector_name' => 'Sector Name',
            'have_commission' => 'Have Commission',
        ];
    }

    protected function mapColumn(string $key, mixed $service): mixed
    {
        /** @var MaintenanceService $service */
        return match ($key) {
            'id' => $service->id,
            'name' => $service->name,
            'sale_currency' => $service->sale_currency,
            'service_price' => $service->service_price,
            'max_discount_type' => $service->max_discount_type,
            'max_discount_value' => $service->max_discount_value,
            'sector_name' => $service->sector?->name,
            'have_commission' => $service->have_commission ? 'Yes' : 'No',
            default => null,
        };
    }
}
