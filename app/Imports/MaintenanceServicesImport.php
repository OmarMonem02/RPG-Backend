<?php

namespace App\Imports;

use App\Models\MaintenanceService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class MaintenanceServicesImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, WithBatchInserts, WithChunkReading
{
    use SkipsErrors;

    public function model(array $row): ?MaintenanceService
    {
        return new MaintenanceService([
            'name'                           => $row['name'] ?? null,
            'currency_pricing'               => $row['currency_pricing'] ?? null,
            'service_price'                  => $row['service_price'] ?? null,
            'max_discount_type'              => $row['max_discount_type'] ?? null,
            'max_discount_value'             => $row['max_discount_value'] ?? null,
            'maintenance_service_sector_id'  => $row['sector_id'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'service_price' => 'nullable|numeric|min:0',
        ];
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
