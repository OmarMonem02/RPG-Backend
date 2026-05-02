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
                $brandId = (int) $row['brand_id'];
            } else {
                // Try to find brand by name
                $brand = Brand::where('name', $row['brand_id'])->first();
                $brandId = $brand ? $brand->id : null;
            }
        }

        $lookupAttributes = [
            'brand_id' => $brandId,
            'model' => $row['model'] ?? null,
            'year' => $row['year'] ?? null,
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
                [$brandId, $row['model'] ?? null, $row['year'] ?? null],
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
            'model' => $row['model'] ?? null,
            'year' => $row['year'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'brand_id' => 'nullable',
            'model' => 'required',
            'year' => 'required|integer|min:1900|max:2100',
        ];
    }

}
