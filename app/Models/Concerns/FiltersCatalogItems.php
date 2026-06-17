<?php

namespace App\Models\Concerns;

use App\Support\CaseInsensitiveLike;
use App\Support\SqlExpressions;

trait FiltersCatalogItems
{
    public function scopeByItemStatus($query, ?string $status)
    {
        return $status ? $query->where('item_status', $status) : $query;
    }

    public function scopeBySize($query, ?string $size)
    {
        if (! $size) {
            return $query;
        }

        return CaseInsensitiveLike::where($query, 'size', $size);
    }

    public function scopeByColor($query, ?string $color)
    {
        if (! $color) {
            return $query;
        }

        return CaseInsensitiveLike::where($query, 'color', $color);
    }

    public function scopeByUniversalFlag($query, ?bool $universal)
    {
        if ($universal === null) {
            return $query;
        }

        return $query->where('universal', $universal);
    }

    public function scopeByStockRange($query, ?int $min = null, ?int $max = null)
    {
        if ($min !== null) {
            $query = $query->where('stock_quantity', '>=', $min);
        }
        if ($max !== null) {
            $query = $query->where('stock_quantity', '<=', $max);
        }

        return $query;
    }

    public function scopeByCostPrice($query, ?float $minPrice = null, ?float $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query = $query->where('cost_price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query = $query->where('cost_price', '<=', $maxPrice);
        }

        return $query;
    }

    public function scopeByMaxDiscount($query, ?float $min = null, ?float $max = null)
    {
        if ($min !== null) {
            $query = $query->where('max_discount_value', '>=', $min);
        }
        if ($max !== null) {
            $query = $query->where('max_discount_value', '<=', $max);
        }

        return $query;
    }

    public function scopeByProfitRange($query, ?float $min = null, ?float $max = null)
    {
        $profitSql = SqlExpressions::profitAmount();

        if ($min !== null) {
            $query = $query->whereRaw("{$profitSql} >= ?", [$min]);
        }
        if ($max !== null) {
            $query = $query->whereRaw("{$profitSql} <= ?", [$max]);
        }

        return $query;
    }

    public function scopeByProfitPercentRange($query, ?float $min = null, ?float $max = null)
    {
        $expression = SqlExpressions::profitPercent();

        if ($min !== null) {
            $query = $query->whereRaw("({$expression}) >= ?", [$min]);
        }
        if ($max !== null) {
            $query = $query->whereRaw("({$expression}) <= ?", [$max]);
        }

        return $query;
    }

    public function scopeByStockAlertLevel($query, ?string $level)
    {
        if (! $level || $level === 'all') {
            return $query;
        }

        if ($level === 'out') {
            return $query->where('stock_quantity', '<=', 0);
        }

        if ($level === 'low') {
            return $query->where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_alarm');
        }

        return $query;
    }
}
