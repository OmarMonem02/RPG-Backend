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
        $types = Brand::mergeTypes(
            null,
            Brand::parseTypes($row['types'] ?? $row['type'] ?? null)
        );

        if ($types === []) {
            return null;
        }

        $lookupAttributes = [
            'name' => $row['name'] ?? null,
        ];

        $fillAttributes = [
            'name' => $row['name'] ?? null,
            'types' => $types,
        ];

        $restored = $this->restoreMatchingRecord(
            Brand::class,
            $lookupAttributes,
            $fillAttributes,
            'brand',
            $this->getRowNumber(),
            $lookupAttributes
        );

        if ($restored) {
            $restored->types = Brand::mergeTypes($restored->types, $types);
            $restored->save();

            return null;
        }

        if ($this->shouldSkipDuplicate(
            Brand::class,
            $lookupAttributes,
            [$row['name'] ?? null],
            'brand',
            $this->getRowNumber(),
            $lookupAttributes
        )) {
            return null;
        }

        $this->recordCreated();

        return new Brand($fillAttributes);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'types' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:100',
        ];
    }

}
