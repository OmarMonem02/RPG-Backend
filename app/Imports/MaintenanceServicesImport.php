<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportResults;
use App\Models\MaintenanceService;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;

class MaintenanceServicesImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use RemembersRowNumber;
    use SkipsErrors;
    use TracksImportResults;

    public function model(array $row): ?MaintenanceService
    {
        $lookupAttributes = [
            'name' => $row['name'] ?? null,
            'maintenance_service_sector_id' => $row['sector_id'] ?? null,
        ];
        $fillAttributes = [
            'name' => $row['name'] ?? null,
            'currency_pricing' => $row['currency_pricing'] ?? null,
            'service_price' => $row['service_price'] ?? null,
            'max_discount_type' => $row['max_discount_type'] ?? null,
            'max_discount_value' => $row['max_discount_value'] ?? null,
            'maintenance_service_sector_id' => $row['sector_id'] ?? null,
        ];

        if ($this->restoreMatchingRecord(
            MaintenanceService::class,
            $lookupAttributes,
            $fillAttributes,
            'maintenance service',
            $this->getRowNumber(),
            [
                'name' => $row['name'] ?? null,
                'sector_id' => $row['sector_id'] ?? null,
            ]
        )) {
            return null;
        }

        if ($this->shouldSkipDuplicate(
            MaintenanceService::class,
            $lookupAttributes,
            [$row['name'] ?? null, $row['sector_id'] ?? null],
            'maintenance service',
            $this->getRowNumber(),
            [
                'name' => $row['name'] ?? null,
                'sector_id' => $row['sector_id'] ?? null,
            ]
        )) {
            return null;
        }

        $this->recordCreated();

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

}
