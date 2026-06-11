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
     * @param  array<int, string>|string|null  $tags
     * @return array<int, string>|null
     */
    public static function parseTagsQueryParam(null|array|string $tags): ?array
    {
        if ($tags === null) {
            return null;
        }

        if (is_array($tags)) {
            $tags = implode(',', $tags);
        }

        if (trim($tags) === '') {
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
        $like = '%' . self::escapeLikeNeedle($needle) . '%';
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $query->whereNotNull('tags')->whereRaw(
                'EXISTS (SELECT 1 FROM json_each(tags) WHERE LOWER(json_each.value) LIKE ? ESCAPE \'\\\')',
                [$like]
            );

            return;
        }

        if ($driver === 'pgsql') {
            $query->whereNotNull('tags')->whereRaw(
                "EXISTS (
                    SELECT 1 FROM json_array_elements_text(tags) AS tag_rows(tag_value)
                    WHERE LOWER(tag_rows.tag_value) LIKE ? ESCAPE '\\\\'
                )",
                [$like]
            );

            return;
        }

        if ($driver === 'mysql' && self::connectionSupportsJsonTable($connection)) {
            $query->whereNotNull('tags')->whereRaw(
                "EXISTS (
                    SELECT 1 FROM JSON_TABLE(
                        tags,
                        '\$[*]' COLUMNS(tag_value VARCHAR(255) PATH '\$')
                    ) AS tag_rows
                    WHERE LOWER(tag_rows.tag_value) LIKE ? ESCAPE '\\\\'
                )",
                [$like]
            );

            return;
        }

        // MariaDB (often via DB_CONNECTION=mysql) and other drivers: match JSON text.
        $query->whereNotNull('tags')->whereRaw(
            'LOWER(CAST(tags AS CHAR)) LIKE ? ESCAPE \'\\\\\'',
            [$like]
        );
    }

    /**
     * JSON_TABLE exists on MySQL 8.0.4+ but not on MariaDB, which Laravel may still
     * report with the mysql PDO driver name.
     */
    protected static function connectionSupportsJsonTable($connection): bool
    {
        static $cache = [];

        $name = $connection->getName();
        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }

        $versionRow = $connection->selectOne('SELECT VERSION() AS version');
        $version = strtolower((string) ($versionRow->version ?? ''));

        if ($version === '' || str_contains($version, 'mariadb')) {
            return $cache[$name] = false;
        }

        $mysqlVersion = preg_replace('/[^0-9.].*$/', '', $version) ?: '0';

        return $cache[$name] = version_compare($mysqlVersion, '8.0.4', '>=');
    }

    protected static function escapeLikeNeedle(string $needle): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);
    }
}
