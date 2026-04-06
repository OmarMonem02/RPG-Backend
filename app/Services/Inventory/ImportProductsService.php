<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportProductsService
{
    public function __construct(
        private readonly CreateProductService $createProductService,
        private readonly UpdateProductService $updateProductService,
    ) {
    }

    public function execute(UploadedFile $file, string $mode = 'upsert'): array
    {
        return DB::transaction(function () use ($file, $mode): array {
            $rows = $this->parseFile($file);
            $created = 0;
            $updated = 0;

            foreach ($rows as $index => $row) {
                $payload = $this->normalizeRow($row);
                $validator = Validator::make($payload, [
                    'sku' => ['required', 'string'],
                    'name' => ['required', 'string'],
                    'type' => ['required', 'in:part,accessory'],
                    'category_id' => ['required', 'integer', 'exists:categories,id'],
                    'brand_id' => ['required', 'integer', 'exists:brands,id'],
                    'qty' => ['required', 'integer', 'min:0'],
                    'selling_price' => ['required', 'numeric', 'min:0'],
                    'cost_price' => ['nullable', 'numeric', 'min:0'],
                    'cost_price_usd' => ['nullable', 'numeric', 'min:0'],
                    'max_discount_type' => ['required', 'in:percentage,fixed'],
                    'max_discount_value' => ['required', 'numeric', 'min:0'],
                    'is_universal' => ['nullable', 'boolean'],
                ]);

                if ($validator->fails()) {
                    throw ValidationException::withMessages([
                        "row_{$index}" => $validator->errors()->all(),
                    ]);
                }

                $product = Product::query()->where('sku', $payload['sku'])->first();

                if ($product === null) {
                    if ($mode === 'update') {
                        continue;
                    }

                    $this->createProductService->execute($payload);
                    $created++;
                    continue;
                }

                if ($mode === 'insert') {
                    continue;
                }

                $this->updateProductService->execute($product, $payload);
                $updated++;
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'processed' => count($rows),
            ];
        });
    }

    private function parseFile(UploadedFile $file): array
    {
        $delimiter = in_array($file->getClientOriginalExtension(), ['xls', 'txt'], true) ? "\t" : ',';
        $handle = fopen($file->getRealPath(), 'rb');
        $rows = [];
        $header = null;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($header === null) {
                $header = array_map('trim', $data);
                continue;
            }

            if ($data === [null]) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($data, count($header), null));
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeRow(array $row): array
    {
        return array_filter([
            'type' => $row['type'] ?? null,
            'name' => $row['name'] ?? null,
            'sku' => $row['sku'] ?? null,
            'part_number' => $row['part_number'] ?? null,
            'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
            'brand_id' => isset($row['brand_id']) ? (int) $row['brand_id'] : null,
            'qty' => isset($row['qty']) ? (int) $row['qty'] : null,
            'cost_price' => ($row['cost_price'] ?? '') !== '' ? (float) $row['cost_price'] : null,
            'selling_price' => isset($row['selling_price']) ? (float) $row['selling_price'] : null,
            'cost_price_usd' => ($row['cost_price_usd'] ?? '') !== '' ? (float) $row['cost_price_usd'] : null,
            'max_discount_type' => $row['max_discount_type'] ?? null,
            'max_discount_value' => isset($row['max_discount_value']) ? (float) $row['max_discount_value'] : null,
            'is_universal' => in_array(strtolower((string) ($row['is_universal'] ?? '0')), ['1', 'true', 'yes'], true),
            'description' => $row['description'] ?? null,
            'units' => $this->decodeJsonColumn($row['units_json'] ?? null),
            'bike_ids' => $this->decodeIdList($row['bike_ids'] ?? null),
        ], static fn ($value) => $value !== null);
    }

    private function decodeJsonColumn(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function decodeIdList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(static fn (string $id) => (int) trim($id))
            ->filter()
            ->values()
            ->all();
    }
}
