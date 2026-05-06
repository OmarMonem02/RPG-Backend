<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportResults;
use App\Models\BikeBlueprint;
use App\Models\Brand;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;

class BikeBlueprintsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use RemembersRowNumber;
    use SkipsErrors;
    use TracksImportResults;

    public function model(array $row): ?BikeBlueprint
    {
        $brandId = null;

        if (!empty($row['brand_id'])) {
            // Check if it's a number (ID) or string (name)
            if (is_numeric($row['brand_id'])) {
                // Verify the brand actually exists in this environment
                $brand = Brand::find((int) $row['brand_id']);
                $brandId = $brand ? $brand->id : null;
            } else {
                // Try to find brand by name
                $brand = Brand::where('name', $row['brand_id'])->first();
                $brandId = $brand ? $brand->id : null;
            }
        }

        // Cast explicitly: Excel may read numeric-looking model names as integers
        $model = isset($row['model']) && $row['model'] !== '' ? (string) $row['model'] : null;
        $year  = isset($row['year'])  && $row['year']  !== '' ? (int)    $row['year']  : null;

        $lookupAttributes = [
            'brand_id' => $brandId,
            'model'    => $model,
            'year'     => $year,
        ];

        if ($this->restoreMatchingRecord(
            BikeBlueprint::class,
            $lookupAttributes,
            $lookupAttributes,
            'bike blueprint',
            $this->getRowNumber(),
            $lookupAttributes
        )) {
            return null;
        }

        if (
            $this->shouldSkipDuplicate(
                BikeBlueprint::class,
                $lookupAttributes,
                [$brandId, $model, $year],
                'bike blueprint',
                $this->getRowNumber(),
                $lookupAttributes
            )
        ) {
            return null;
        }

        $this->recordCreated();

        return new BikeBlueprint([
            'brand_id' => $brandId,
            'model'    => $model,
            'year'     => $year,
        ]);
    }

    public function rules(): array
    {
        return [
            'brand_id' => 'nullable',
            'model'    => 'required|max:255',   // cast to string before validation, so no |string rule needed
            'year'     => 'required|integer|min:1900|max:2100',
        ];
    }

}
