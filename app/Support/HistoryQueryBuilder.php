<?php

namespace App\Support;

use App\Models\History;
use Illuminate\Database\Eloquent\Builder;

class HistoryQueryBuilder
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['entity_type'])) {
            $modelClass = HistoryCatalog::modelClassForEntityType($filters['entity_type']);
            if ($modelClass) {
                $query->where('model_type', $modelClass);
            }
        } elseif (! empty($filters['model_type'])) {
            $query->where('model_type', 'like', '%' . $filters['model_type'] . '%');
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['model_id'])) {
            $query->where('model_id', $filters['model_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search) {
                if (ctype_digit($search)) {
                    $builder->where('model_id', (int) $search);
                }

                $builder->orWhereHas('user', function (Builder $userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: int, creates: int, updates: int, deletes: int}
     */
    public static function summarize(array $filters): array
    {
        $query = self::apply(History::query(), $filters);

        $counts = (clone $query)
            ->selectRaw('action, COUNT(*) as aggregate')
            ->groupBy('action')
            ->pluck('aggregate', 'action');

        return [
            'total' => (int) (clone $query)->count(),
            'creates' => (int) ($counts['create'] ?? 0),
            'updates' => (int) ($counts['update'] ?? 0),
            'deletes' => (int) ($counts['delete'] ?? 0),
        ];
    }
}
