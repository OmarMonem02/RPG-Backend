<?php

namespace App\Support;

use Illuminate\Cache\TaggableStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiCache
{
    /**
     * @param  array<string>  $tags
     */
    public static function remember(string $key, int $ttlSeconds, array $tags, callable $callback): mixed
    {
        if (self::supportsTags()) {
            return Cache::tags($tags)->remember($key, $ttlSeconds, $callback);
        }

        return Cache::remember(self::scopedKey($key, $tags), $ttlSeconds, $callback);
    }

    /**
     * @param  array<string>  $tags
     */
    public static function has(string $key, array $tags): bool
    {
        if (self::supportsTags()) {
            return Cache::tags($tags)->has($key);
        }

        return Cache::has(self::scopedKey($key, $tags));
    }

    /**
     * @param  array<string>  $tags
     */
    public static function invalidateTags(array $tags): void
    {
        $tags = array_values(array_unique($tags));

        if ($tags === []) {
            return;
        }

        if (self::supportsTags()) {
            Cache::tags($tags)->flush();
            return;
        }

        foreach ($tags as $tag) {
            $versionKey = self::tagVersionKey($tag);
            $currentVersion = (int) Cache::get($versionKey, 1);
            Cache::forever($versionKey, $currentVersion + 1);
        }
    }

    public static function listKey(string $entity, Request $request, string $prefix = 'list'): string
    {
        return sprintf(
            '%s:%s:%s',
            $entity,
            $prefix,
            self::hashQuery($request->query())
        );
    }

    public static function detailKey(string $entity, string|int $id): string
    {
        return sprintf('%s:detail:%s', $entity, (string) $id);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function hashQuery(array $query): string
    {
        $normalized = self::normalize($query);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return sha1($json ?: '{}');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private static function normalize(array $query): array
    {
        ksort($query);

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $query[$key] = self::normalize($value);
                continue;
            }

            if (is_bool($value)) {
                $query[$key] = $value ? 'true' : 'false';
                continue;
            }

            if ($value === null) {
                $query[$key] = 'null';
                continue;
            }

            $query[$key] = (string) $value;
        }

        return $query;
    }

    /**
     * @param  array<string>  $tags
     */
    private static function scopedKey(string $key, array $tags): string
    {
        $versionState = [];

        foreach (array_values(array_unique($tags)) as $tag) {
            $versionState[$tag] = (int) Cache::get(self::tagVersionKey($tag), 1);
        }

        ksort($versionState);

        return sprintf('%s:v:%s', $key, sha1(json_encode($versionState) ?: '{}'));
    }

    private static function tagVersionKey(string $tag): string
    {
        return "api_cache:tag_version:{$tag}";
    }

    private static function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
