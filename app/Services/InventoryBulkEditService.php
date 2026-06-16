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

    private const PRICE_FIELDS = ['sale_price', 'cost_price'];

    private const STOCK_FIELDS = ['stock_quantity', 'low_stock_alarm'];

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
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }
        if (! empty($filters['brand_id'])) {
            $query->byBrand((int) $filters['brand_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->byCategory((int) $filters['category_id']);
        }
        if (! empty($filters['currency'])) {
            $query->byCurrency(strtoupper((string) $filters['currency']));
        }

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
     * @param  array<string, array{mode: string, value: float|int|string}>  $changes
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public function preview(string $modelClass, array $ids, array $changes): array
    {
        $records = $modelClass::query()->whereIn('id', $ids)->orderBy('id')->get();
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
     * @param  array<string, array{mode: string, value: float|int|string}>  $changes
     * @return array{updated: int, rows: array<int, array<string, mixed>>}
     */
    public function apply(string $modelClass, array $ids, array $changes): array
    {
        $records = $modelClass::query()->whereIn('id', $ids)->orderBy('id')->get();
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

                $record->update($payload);
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
        $allowed = array_flip(BulkInventoryEditRequest::ALLOWED_FIELDS);
        $updated = collect();

        \DB::transaction(function () use ($modelClass, $updates, $allowed, &$updated) {
            foreach ($updates as $update) {
                $id = (int) $update['id'];
                unset($update['id']);

                $payload = array_intersect_key($update, $allowed);
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
     * @param  Model&Product|SparePart  $record
     * @param  array<string, array{mode: string, value: float|int|string}>  $changes
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

            $oldRaw = $record->getAttribute($field);
            $isPrice = in_array($field, self::PRICE_FIELDS, true);
            $old = $isPrice ? (float) $oldRaw : (int) $oldRaw;
            $new = $this->applyChange($old, $change, $isPrice);

            $before[$field] = $isPrice ? round($old, 2) : $old;
            $after[$field] = $isPrice ? round($new, 2) : $new;

            if ($before[$field] !== $after[$field]) {
                $changedFields[] = $field;
            }
        }

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
     * @param  array{mode: string, value: float|int|string}  $change
     */
    private function applyChange(float|int $old, array $change, bool $isPrice): float|int
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
