<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportResults;
use App\Models\BikeForSale;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;

class BikesImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use RemembersRowNumber;
    use SkipsErrors;
    use TracksImportResults;

    public function model(array $row): ?BikeForSale
    {
        $lookupAttributes = ['vin' => $row['vin'] ?? null];
        $fillAttributes = [
            'bike_blueprint_id' => $row['blueprint_id'] ?? null,
            'vin' => $row['vin'] ?? null,
            'mileage' => $row['mileage'] ?? null,
            'status' => $row['status'] ?? null,
            'currency_pricing' => $row['currency_pricing'] ?? null,
            'cost_price' => $row['cost_price'] ?? null,
            'sale_price' => $row['sale_price'] ?? null,
            'max_discount_type' => $row['max_discount_type'] ?? null,
            'max_discount_value' => $row['max_discount_value'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];

        if ($this->restoreMatchingRecord(
            BikeForSale::class,
            $lookupAttributes,
            $fillAttributes,
            'bike',
            $this->getRowNumber(),
            $lookupAttributes
        )) {
            return null;
        }

        if ($this->shouldSkipDuplicate(
            BikeForSale::class,
            $lookupAttributes,
            [$row['vin'] ?? null],
            'bike',
            $this->getRowNumber(),
            $lookupAttributes
        )) {
            return null;
        }

        $this->recordCreated();

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
            'vin'          => 'required|string|max:50',
            'cost_price'   => 'nullable|numeric|min:0',
            'sale_price'   => 'nullable|numeric|min:0',
        ];
    }

}
