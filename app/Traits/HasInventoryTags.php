<?php

namespace App\Traits;

trait HasInventoryTags
{
    protected static function bootHasInventoryTags(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('tags')) {
                $model->tags = self::normalizeTags($model->tags);
            }
        });
    }

    /**
     * @return array<int, string>|null
     */
    public static function parseTagsQueryParam(?string $tags): ?array
    {
        if ($tags === null || trim($tags) === '') {
            return null;
        }

        $parsed = array_values(array_filter(
            array_map('trim', explode(',', $tags)),
            fn (string $tag) => $tag !== ''
        ));

        return $parsed === [] ? null : $parsed;
    }

    /**
     * Normalize tags: trim, drop empties, dedupe case-insensitively.
     *
     * @param  array<int, string>|null  $tags
     * @return array<int, string>|null
     */
    public static function normalizeTags(?array $tags): ?array
    {
        if ($tags === null || $tags === []) {
            return null;
        }

        $normalized = [];
        $seen = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $trimmed = trim($tag);
            if ($trimmed === '') {
                continue;
            }

            $key = strtolower($trimmed);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $trimmed;
        }

        return $normalized === [] ? null : array_values($normalized);
    }

    public function scopeByTags($query, ?array $filterTags)
    {
        if (empty($filterTags)) {
            return $query;
        }

        foreach ($filterTags as $tag) {
            $needle = strtolower(trim((string) $tag));
            if ($needle === '') {
                continue;
            }

            $query->where(function ($q) use ($needle) {
                self::applyTagLikeMatch($q, $needle);
            });
        }

        return $query;
    }

    public function scopeSearchTags($query, string $search)
    {
        $needle = strtolower(trim($search));
        if ($needle === '') {
            return $query;
        }

        return $query->orWhere(function ($q) use ($needle) {
            self::applyTagLikeMatch($q, $needle);
        });
    }

    protected static function applyTagLikeMatch($query, string $needle): void
    {
        $like = '%' . $needle . '%';
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->whereNotNull('tags')->whereRaw(
                'EXISTS (SELECT 1 FROM json_each(tags) WHERE LOWER(json_each.value) LIKE ?)',
                [$like]
            );

            return;
        }

        // MariaDB does not support MySQL's JSON_TABLE; match against JSON text instead.
        $query->whereNotNull('tags')->whereRaw(
            'LOWER(CAST(tags AS CHAR)) LIKE ?',
            [$like]
        );
    }
}
