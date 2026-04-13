<?php

namespace App\Exports;

use App\Models\MaintenanceService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaintenanceServicesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Maintenance Services';
    }

    public function query()
    {
        return MaintenanceService::query()->with(['sector']);
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
            'Sector ID',
            'Sector Name',
            'Created At',
            'Updated At',
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
            $service->maintenance_service_sector_id,
            $service->sector?->name,
            $service->created_at?->format('Y-m-d H:i:s'),
            $service->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
