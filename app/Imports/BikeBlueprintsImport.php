<?php

namespace App\Imports;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithChunkReading;
class BikeBlueprintsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, WithChunkReading
{
    use SkipsErrors;

    public function model(array $row): ?BikeBlueprint
    {
        $brandId = null;
        
        if (!empty($row['brand_id'])) {
            // Check if it's a number (ID) or string (name)
            if (is_numeric($row['brand_id'])) {
                $brandId = (int) $row['brand_id'];
            } else {
                // Try to find brand by name
                $brand = Brand::where('name', $row['brand_id'])->first();
                $brandId = $brand ? $brand->id : null;
            }
        }
        
        return new BikeBlueprint([
            'brand_id' => $brandId,
            'model'    => $row['model'] ?? null,
            'year'     => $row['year'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'brand_id' => 'nullable',
            'model'    => 'nullable',
            'year'     => 'required|integer|min:1900|max:2100',
        ];
    }


    public function chunkSize(): int
    {
        return 500;
    }
}
