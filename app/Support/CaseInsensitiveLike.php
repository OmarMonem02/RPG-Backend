<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class CaseInsensitiveLike
{
    public static function pattern(string $term): string
    {
        $needle = mb_strtolower(trim($term));

        return '%' . str_replace(['%', '_'], ['\\%', '\\_'], $needle) . '%';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function where(Builder $query, string $column, string $term): Builder
    {
        return $query->whereRaw('LOWER(' . $column . ') LIKE ?', [self::pattern($term)]);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function orWhere(Builder $query, string $column, string $term): Builder
    {
        return $query->orWhereRaw('LOWER(' . $column . ') LIKE ?', [self::pattern($term)]);
    }
}
