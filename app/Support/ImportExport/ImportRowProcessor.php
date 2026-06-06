<?php

namespace App\Support\ImportExport;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\MaintenanceServiceSector;
use App\Models\ProductCategory;
use App\Models\SparePartCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportRowProcessor
{
    /** @var array<string, bool> */
    private array $seenDuplicateKeys = [];

    public function process(string $entity, array $definition, array $row, int $rowNumber, bool $persist): array
    {
        $row = $this->normalizeKeys($row);
        $issues = $this->validate($definition, $row, $rowNumber);
        $resolved = [];
        $related = [];

        if ($issues === []) {
            [$resolved, $related, $referenceIssues] = $this->resolveReferences($entity, $row, $rowNumber);
            $issues = array_merge($issues, $referenceIssues);
        }

        if ($issues !== []) {
            return $this->result($rowNumber, 'invalid', 'error', $row, $issues);
        }

        $lookup = $this->lookupAttributes($entity, $row, $resolved);
        $duplicateKey = $this->duplicateKey($lookup);
        $label = $definition['duplicate_label'];

        if (isset($this->seenDuplicateKeys[$duplicateKey])) {
            return $this->result($rowNumber, 'duplicate', 'warning', $row, [
                $this->issue($rowNumber, 'duplicate', "Duplicate {$label} inside this file."),
            ]);
        }

        $this->seenDuplicateKeys[$duplicateKey] = true;

        $modelClass = $definition['model'];
        $existing = $modelClass::withTrashed()->where($lookup)->first();

        if ($existing && ! $this->isTrashed($existing)) {
            return $this->result($rowNumber, 'duplicate', 'warning', $row, [
                $this->issue($rowNumber, 'duplicate', "Existing {$label} will be skipped."),
            ]);
        }

        $attributes = $this->attributes($entity, $row, $resolved);

        if (! $persist) {
            return $this->result($rowNumber, $existing ? 'restorable' : 'valid', 'success', $row, [], [
                'action' => $existing ? 'restore' : 'create',
            ]);
        }

        if ($existing) {
            $existing->fill($attributes);
            $existing->restore();
            $existing->save();
            $this->syncRelated($entity, $existing, $related);

            return $this->result($rowNumber, 'restored', 'success', $row, [], [
                'action' => 'restore',
                'record_id' => $existing->getKey(),
            ]);
        }

        /** @var Model $created */
        $created = $modelClass::create($attributes);
        $this->syncRelated($entity, $created, $related);

        return $this->result($rowNumber, 'created', 'success', $row, [], [
            'action' => 'create',
            'record_id' => $created->getKey(),
        ]);
    }

    public function summarize(array $rows, string $message): array
    {
        $counts = [
            'total_rows' => count($rows),
            'valid_count' => 0,
            'invalid_count' => 0,
            'duplicate_count' => 0,
            'created_count' => 0,
            'restored_count' => 0,
            'skipped_count' => 0,
        ];

        foreach ($rows as $row) {
            match ($row['status']) {
                'valid', 'restorable' => $counts['valid_count']++,
                'invalid' => $counts['invalid_count']++,
                'duplicate' => $counts['duplicate_count']++,
                'created' => $counts['created_count']++,
                'restored' => $counts['restored_count']++,
                default => null,
            };

            if (in_array($row['status'], ['invalid', 'duplicate'], true)) {
                $counts['skipped_count']++;
            }
        }

        return [
            'message' => $message,
            ...$counts,
        ];
    }

    private function validate(array $definition, array $row, int $rowNumber): array
    {
        $rules = [];

        foreach ($definition['columns'] as $column) {
            $columnRules = [$column['required'] ? 'required' : 'nullable'];
            $columnRules[] = match ($column['type']) {
                'integer' => 'integer',
                'decimal' => 'numeric',
                'boolean' => 'in:yes,no,true,false,1,0',
                default => 'string',
            };

            if ($column['type'] === 'select' && $column['accepted_values'] !== []) {
                $columnRules[] = 'in:' . implode(',', $column['accepted_values']);
            }

            $rules[$column['key']] = implode('|', $columnRules);
        }

        $validator = Validator::make($row, $rules);

        return collect($validator->errors()->all())
            ->map(fn (string $message) => $this->issue($rowNumber, 'validation', $message))
            ->values()
            ->all();
    }

    private function resolveReferences(string $entity, array $row, int $rowNumber): array
    {
        $resolved = [];
        $related = [];
        $issues = [];

        if (in_array($entity, ['products', 'spare_parts'], true)) {
            $brandType = $entity === 'products' ? 'products' : 'spare_parts';
            if ($row['brand_name'] ?? null) {
                $resolved['brand_id'] = $this->resolveBrand($row['brand_name'], $brandType, $rowNumber, $issues);
            }

            if ($row['category_name'] ?? null) {
                $resolved[$entity === 'products' ? 'products_category_id' : 'spare_parts_category_id'] = $entity === 'products'
                    ? $this->resolveByName(ProductCategory::class, $row['category_name'], 'product category', $rowNumber, $issues)
                    : $this->resolveByName(SparePartCategory::class, $row['category_name'], 'spare part category', $rowNumber, $issues);
            }
        }

        if ($entity === 'maintenance_services' && ($row['sector_name'] ?? null)) {
            $resolved['maintenance_service_sector_id'] = $this->resolveByName(MaintenanceServiceSector::class, $row['sector_name'], 'maintenance sector', $rowNumber, $issues);
        }

        if (in_array($entity, ['bikes', 'bike_blueprints'], true)) {
            $resolved['brand_id'] = $this->resolveBrand($row['brand_name'] ?? null, 'bikes', $rowNumber, $issues);
        }

        if ($entity === 'bikes' && ($resolved['brand_id'] ?? null)) {
            $resolved['bike_blueprint_id'] = $this->resolveBlueprint($resolved['brand_id'], $row['model'] ?? null, $row['year'] ?? null, $rowNumber, $issues);
        }

        if (in_array($entity, ['products', 'spare_parts'], true) && ($row['bike_blueprints'] ?? null)) {
            $related['bike_blueprint_ids'] = $this->resolveBlueprintList($row['bike_blueprints'], $rowNumber, $issues);
        }

        return [$resolved, $related, $issues];
    }

    private function resolveBrand(?string $name, string $type, int $rowNumber, array &$issues): ?int
    {
        if (! $name) {
            return null;
        }

        $query = Brand::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])
            ->where('type', $type);

        $count = (clone $query)->count();

        if ($count === 1) {
            return $query->value('id');
        }

        $issues[] = $this->issue($rowNumber, 'reference', $count === 0
            ? "Brand '{$name}' was not found for {$type}."
            : "Brand '{$name}' is ambiguous for {$type}.");

        return null;
    }

    private function resolveByName(string $modelClass, ?string $name, string $label, int $rowNumber, array &$issues): ?int
    {
        $query = $modelClass::query()->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $name))]);
        $count = (clone $query)->count();

        if ($count === 1) {
            return $query->value('id');
        }

        $issues[] = $this->issue($rowNumber, 'reference', $count === 0
            ? ucfirst($label) . " '{$name}' was not found."
            : ucfirst($label) . " '{$name}' is ambiguous.");

        return null;
    }

    private function resolveBlueprint(int $brandId, ?string $model, mixed $year, int $rowNumber, array &$issues): ?int
    {
        $query = BikeBlueprint::query()
            ->where('brand_id', $brandId)
            ->whereRaw('LOWER(model) = ?', [Str::lower(trim((string) $model))])
            ->where('year', (int) $year);

        $count = (clone $query)->count();

        if ($count === 1) {
            return $query->value('id');
        }

        $issues[] = $this->issue($rowNumber, 'reference', $count === 0
            ? "Bike blueprint '{$model} {$year}' was not found for the selected brand."
            : "Bike blueprint '{$model} {$year}' is ambiguous for the selected brand.");

        return null;
    }

    private function resolveBlueprintList(string $value, int $rowNumber, array &$issues): array
    {
        $ids = [];

        foreach (array_filter(array_map('trim', explode(';', $value))) as $item) {
            $parts = array_map('trim', explode('|', $item));
            if (count($parts) !== 3) {
                $issues[] = $this->issue($rowNumber, 'reference', "Bike blueprint '{$item}' must be written as Brand | Model | Year.");
                continue;
            }

            $brandId = $this->resolveBrand($parts[0], 'bikes', $rowNumber, $issues);
            if ($brandId) {
                $id = $this->resolveBlueprint($brandId, $parts[1], $parts[2], $rowNumber, $issues);
                if ($id) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function lookupAttributes(string $entity, array $row, array $resolved): array
    {
        return match ($entity) {
            'products', 'spare_parts' => ['sku' => $row['sku']],
            'maintenance_services' => [
                'name' => $row['name'],
                'maintenance_service_sector_id' => $resolved['maintenance_service_sector_id'] ?? null,
            ],
            'bikes' => ['vin' => $row['vin']],
            'bike_blueprints' => [
                'brand_id' => $resolved['brand_id'],
                'model' => $row['model'],
                'year' => (int) $row['year'],
            ],
            'brands' => ['name' => $row['name'], 'type' => $row['type'] ?? null],
        };
    }

    private function attributes(string $entity, array $row, array $resolved): array
    {
        return match ($entity) {
            'products' => [
                'name' => $row['name'],
                'sku' => $row['sku'],
                'part_number' => $row['part_number'] ?? null,
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'low_stock_alarm' => (int) ($row['low_stock_alarm'] ?? 0),
                'products_category_id' => $resolved['products_category_id'] ?? null,
                'currency_pricing' => $row['currency_pricing'] ?? null,
                'cost_price' => $row['cost_price'] ?? null,
                'sale_price' => $row['sale_price'] ?? null,
                'brand_id' => $resolved['brand_id'] ?? null,
                'max_discount_type' => $row['max_discount_type'] ?? null,
                'max_discount_value' => $row['max_discount_value'] ?? null,
                'universal' => $this->boolean($row['universal'] ?? null),
                'notes' => $row['notes'] ?? null,
            ],
            'spare_parts' => [
                'name' => $row['name'],
                'sku' => $row['sku'],
                'part_number' => $row['part_number'] ?? null,
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'low_stock_alarm' => (int) ($row['low_stock_alarm'] ?? 0),
                'spare_parts_category_id' => $resolved['spare_parts_category_id'] ?? null,
                'currency_pricing' => $row['currency_pricing'] ?? null,
                'cost_price' => $row['cost_price'] ?? null,
                'sale_price' => $row['sale_price'] ?? null,
                'brand_id' => $resolved['brand_id'] ?? null,
                'max_discount_type' => $row['max_discount_type'] ?? null,
                'max_discount_value' => $row['max_discount_value'] ?? null,
                'universal' => $this->boolean($row['universal'] ?? null),
                'notes' => $row['notes'] ?? null,
            ],
            'maintenance_services' => [
                'name' => $row['name'],
                'currency_pricing' => $row['currency_pricing'] ?? null,
                'service_price' => $row['service_price'] ?? null,
                'max_discount_type' => $row['max_discount_type'] ?? null,
                'max_discount_value' => $row['max_discount_value'] ?? null,
                'maintenance_service_sector_id' => $resolved['maintenance_service_sector_id'] ?? null,
            ],
            'bikes' => [
                'bike_blueprint_id' => $resolved['bike_blueprint_id'],
                'vin' => $row['vin'],
                'mileage' => $row['mileage'] ?? null,
                'status' => $row['status'] ?? null,
                'currency_pricing' => $row['currency_pricing'] ?? null,
                'cost_price' => $row['cost_price'] ?? null,
                'sale_price' => $row['sale_price'] ?? null,
                'max_discount_type' => $row['max_discount_type'] ?? null,
                'max_discount_value' => $row['max_discount_value'] ?? null,
                'notes' => $row['notes'] ?? null,
            ],
            'bike_blueprints' => [
                'brand_id' => $resolved['brand_id'],
                'model' => $row['model'],
                'year' => (int) $row['year'],
            ],
            'brands' => [
                'name' => $row['name'],
                'type' => $row['type'] ?? null,
            ],
        };
    }

    private function syncRelated(string $entity, Model $model, array $related): void
    {
        if (in_array($entity, ['products', 'spare_parts'], true) && Arr::has($related, 'bike_blueprint_ids') && method_exists($model, 'bikeBlueprints')) {
            $model->bikeBlueprints()->sync($related['bike_blueprint_ids']);
        }
    }

    private function normalizeKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[Str::snake(trim((string) $key))] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    private function duplicateKey(array $lookup): string
    {
        return md5(json_encode(array_map(fn ($value) => is_string($value) ? Str::lower(trim($value)) : $value, $lookup)));
    }

    private function boolean(mixed $value): bool
    {
        return in_array(Str::lower((string) $value), ['yes', 'true', '1'], true);
    }

    private function isTrashed(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true)
            && method_exists($model, 'trashed')
            && $model->trashed();
    }

    private function result(int $rowNumber, string $status, string $severity, array $data, array $issues = [], array $extra = []): array
    {
        return [
            'row_number' => $rowNumber,
            'status' => $status,
            'severity' => $severity,
            'data' => $data,
            'issues' => $issues,
            ...$extra,
        ];
    }

    private function issue(int $rowNumber, string $code, string $message): array
    {
        return [
            'row_number' => $rowNumber,
            'code' => $code,
            'message' => $message,
        ];
    }
}
