<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportResults;
use App\Models\Brand;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;

class BrandsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use RemembersRowNumber;
    use SkipsErrors;
    use TracksImportResults;

    public function model(array $row): ?Brand
    {
        $lookupAttributes = [
            'name' => $row['name'] ?? null,
            'type' => $row['type'] ?? null,
        ];

        if ($this->restoreMatchingRecord(
            Brand::class,
            $lookupAttributes,
            $lookupAttributes,
            'brand',
            $this->getRowNumber(),
            $lookupAttributes
        )) {
            return null;
        }

        if ($this->shouldSkipDuplicate(
            Brand::class,
            $lookupAttributes,
            [$row['name'] ?? null, $row['type'] ?? null],
            'brand',
            $this->getRowNumber(),
            $lookupAttributes
        )) {
            return null;
        }

        $this->recordCreated();

        return new Brand([
            'name' => $row['name'] ?? null,
            'type' => $row['type'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
        ];
    }

}
