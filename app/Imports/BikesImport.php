<?php

namespace App\Imports;

use App\Models\BikeForSale;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
class BikesImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, WithChunkReading
{
    use SkipsErrors;

    public function model(array $row): ?BikeForSale
    {
        return new BikeForSale([
            'bike_blueprint_id' => $row['blueprint_id'] ?? null,
            'vin'               => $row['vin'] ?? null,
            'mileage'           => $row['mileage'] ?? null,
            'status'            => $row['status'] ?? null,
            'currency_pricing'  => $row['currency_pricing'] ?? null,
            'cost_price'        => $row['cost_price'] ?? null,
            'sale_price'        => $row['sale_price'] ?? null,
            'max_discount_type' => $row['max_discount_type'] ?? null,
            'max_discount_value'=> $row['max_discount_value'] ?? null,
            'notes'             => $row['notes'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'blueprint_id' => 'required|integer|exists:bike_blueprints,id',
            'vin'          => 'nullable|string|max:50',
            'cost_price'   => 'nullable|numeric|min:0',
            'sale_price'   => 'nullable|numeric|min:0',
        ];
    }


    public function chunkSize(): int
    {
        return 500;
    }
}
