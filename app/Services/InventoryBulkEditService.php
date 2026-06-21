<?php

namespace App\Services;

use App\Http\Requests\BulkInventoryEditRequest;
use App\Models\Product;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryBulkEditService
{
    public const MAX_ITEMS = 500;

    public function __construct(
        private readonly CatalogListFilterService $catalogListFilterService,
    ) {}

    private const PRICE_FIELDS = ['sale_price', 'cost_price'];

    private const STOCK_FIELDS = ['stock_quantity', 'low_stock_alarm'];

    private const SCALAR_SET_FIELDS = [
        'item_status',
        'have_commission',
        'max_discount_type',
        'max_discount_value',
    ];

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int>|null  $ids
     * @param  array<string, mixed>|null  $filters
     * @return array<int>
     */
    public function resolveIds(string $modelClass, ?array $ids, ?array $filters, string $categoryColumn = 'products_category_id'): array
    {
        /** @var Builder $query */
        $query = $modelClass::query();

        $filters = $filters ?? [];
        $query = $this->catalogListFilterService->apply($query, $filters, $modelClass);

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $resolved = $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($resolved) === 0) {
            throw ValidationException::withMessages([
                'ids' => ['No items match the selection criteria.'],
            ]);
        }

        if (count($resolved) > self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'ids' => ['Selection exceeds the maximum of '.self::MAX_ITEMS.' items. Narrow your filters or selection.'],
            ]);
        }

        return $resolved;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int>  $ids
     * @param  array<string, array{mode: string, value: mixed}>  $changes
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public function preview(string $modelClass, array $ids, array $changes): array
    {
        $records = $this->loadRecords($modelClass, $ids);
        $rows = [];

        foreach ($records as $record) {
            $row = $this->computeRow($record, $changes);
            if (! empty($row['changed_fields'])) {
                $rows[] = $row;
            }
        }

        return [
            'total' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int>  $ids
     * @param  array<string, array{mode: string, value: mixed}>  $changes
     * @return array{updated: int, rows: array<int, array<string, mixed>>}
     */
    public function apply(string $modelClass, array $ids, array $changes): array
    {
        $records = $this->loadRecords($modelClass, $ids);
        $rows = [];
        $updated = 0;

        \DB::transaction(function () use ($records, $changes, &$rows, &$updated) {
            foreach ($records as $record) {
                $computed = $this->computeRow($record, $changes);
                if (empty($computed['changed_fields'])) {
                    continue;
                }

                $payload = [];
                foreach ($computed['changed_fields'] as $field) {
                    $payload[$field] = $computed['after'][$field];
                }

                if ($payload !== []) {
                    $record->update($payload);
                }

                $updated++;
                $rows[] = $computed;
            }
        });

        return [
            'updated' => $updated,
            'rows' => $rows,
        ];
    }

    /**
     * Legacy bulk update: array of { id, ...fields }.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, array<string, mixed>>  $updates
     * @return Collection<int, Model>
     */
    public function applyLegacyUpdates(string $modelClass, array $updates): Collection
    {
        $scalarFields = array_flip(array_merge(
            BulkInventoryEditRequest::NUMERIC_FIELDS,
            self::SCALAR_SET_FIELDS,
        ));
        $updated = collect();

        \DB::transaction(function () use ($modelClass, $updates, $scalarFields, &$updated) {
            foreach ($updates as $update) {
                $id = (int) $update['id'];
                unset($update['id']);

                $payload = array_intersect_key($update, $scalarFields);
                if ($payload === []) {
                    continue;
                }

                $record = $modelClass::query()->findOrFail($id);
                $record->update($payload);
                $updated->push($record->fresh());
            }
        });

        return $updated;
    }

    /**
     * @param  Model&Product|SparePart|\App\Models\MaintenancePart  $record
     * @param  array<string, array{mode: string, value: mixed}>  $changes
     * @return array<string, mixed>
     */
    public function computeRow(Model $record, array $changes): array
    {
        $before = [];
        $after = [];
        $changedFields = [];

        foreach ($changes as $field => $change) {
            if (! in_array($field, BulkInventoryEditRequest::ALLOWED_FIELDS, true)) {
                continue;
            }

            if (in_array($field, self::PRICE_FIELDS, true)) {
                $oldRaw = $record->getAttribute($field);
                $old = (float) $oldRaw;
                $new = $this->applyNumericChange($old, $change, true);
                $before[$field] = round($old, 2);
                $after[$field] = round($new, 2);
            } elseif (in_array($field, self::STOCK_FIELDS, true)) {
                $oldRaw = $record->getAttribute($field);
                $old = (int) $oldRaw;
                $new = $this->applyNumericChange($old, $change, false);
                $before[$field] = $old;
                $after[$field] = $new;
            } elseif ($field === 'max_discount_value') {
                $old = (float) $record->getAttribute($field);
                $new = max(0, round((float) ($change['value'] ?? $old), 2));
                $before[$field] = round($old, 2);
                $after[$field] = $new;
            } elseif ($field === 'item_status') {
                $oldRaw = $record->getAttribute($field);
                $old = $oldRaw instanceof \BackedEnum ? $oldRaw->value : (string) $oldRaw;
                $new = (string) ($change['value'] ?? $old);
                $before[$field] = $old;
                $after[$field] = $new;
            } elseif ($field === 'max_discount_type') {
                $old = (string) $record->getAttribute($field);
                $new = (string) ($change['value'] ?? $old);
                $before[$field] = $old;
                $after[$field] = $new;
            } elseif ($field === 'have_commission') {
                $old = (bool) $record->getAttribute($field);
                $new = (bool) ($change['value'] ?? $old);
                $before[$field] = $old;
                $after[$field] = $new;
            } else {
                continue;
            }

            if ($before[$field] !== $after[$field]) {
                $changedFields[] = $field;
            }
        }

        $changedFields = array_values(array_unique($changedFields));

        return [
            'id' => (int) $record->getAttribute('id'),
            'name' => (string) $record->getAttribute('name'),
            'sku' => (string) $record->getAttribute('sku'),
            'sale_currency' => (string) $record->getAttribute('sale_currency'),
            'before' => $before,
            'after' => $after,
            'changed_fields' => $changedFields,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int>  $ids
     * @return Collection<int, Model>
     */
    private function loadRecords(string $modelClass, array $ids): Collection
    {
        return $modelClass::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    /**
     * @param  array{mode: string, value: mixed}  $change
     */
    private function applyNumericChange(float|int $old, array $change, bool $isPrice): float|int
    {
        $mode = (string) ($change['mode'] ?? 'set');
        $value = $change['value'];

        if ($isPrice) {
            $value = (float) $value;

            return match ($mode) {
                'set' => max(0, round($value, 2)),
                'add' => max(0, round($old + $value, 2)),
                'subtract' => max(0, round($old - $value, 2)),
                'percent' => max(0, round($old * (1 + $value / 100), 2)),
                default => $old,
            };
        }

        $value = (int) $value;
        $oldInt = (int) $old;

        return match ($mode) {
            'set' => max(0, $value),
            'add' => max(0, $oldInt + $value),
            'subtract' => max(0, $oldInt - $value),
            default => $oldInt,
        };
    }
}
