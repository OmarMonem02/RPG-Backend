<?php

namespace App\Exports;

use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\MaintenanceService;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaintenanceServicesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use StylesProfessionalSheets;

    public function title(): string
    {
        return 'Maintenance Services';
    }

    public function collection()
    {
        return MaintenanceService::query()->with(['sector'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Currency Pricing',
            'Service Price',
            'Max Discount Type',
            'Max Discount Value',
            'Sector Name',
        ];
    }

    public function map($service): array
    {
        return [
            $service->id,
            $service->name,
            $service->currency_pricing,
            $service->service_price,
            $service->max_discount_type,
            $service->max_discount_value,
            $service->sector?->name,
        ];
    }

}
