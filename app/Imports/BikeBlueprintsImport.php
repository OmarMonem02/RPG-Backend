<?php

namespace App\Imports;

use App\Models\BikeBlueprint;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class BikeBlueprintsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, WithBatchInserts, WithChunkReading
{
    use SkipsErrors;

    public function model(array $row): ?BikeBlueprint
    {
        return new BikeBlueprint([
            'brand_id' => $row['brand_id'] ?? null,
            'model'    => $row['model'] ?? null,
            'year'     => $row['year'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'brand_id' => 'required|integer|exists:brands,id',
            'model'    => 'required|string|max:255',
            'year'     => 'required|integer|min:1900|max:2100',
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
