<?php

namespace App\Services;

use App\Support\FilterRangeParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CatalogListFilterService
{
    /**
     * Apply catalog list filters to a query (mirrors list API + AppliesCatalogItemFilters).
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters, string $modelClass): Builder
    {
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (method_exists($modelClass, 'parseTagsQueryParam')) {
            $tags = $modelClass::parseTagsQueryParam($filters['tags'] ?? null);
            if ($tags) {
                $query->byTags($tags);
            }
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

        if (! empty($filters['bike_brand_id'])) {
            $query->byBikeBrand((int) $filters['bike_brand_id']);
        }

        if (! empty($filters['bike_model'])) {
            $query->byBikeModel((string) $filters['bike_model']);
        }

        if (! empty($filters['bike_year'])) {
            $query->byBikeYear((int) $filters['bike_year']);
        }

        if (! empty($filters['bike_year_from']) || ! empty($filters['bike_year_to'])) {
            $query->byBikeYearRange(
                ! empty($filters['bike_year_from']) ? (int) $filters['bike_year_from'] : null,
                ! empty($filters['bike_year_to']) ? (int) $filters['bike_year_to'] : null,
            );
        }

        if (FilterRangeParser::parseBooleanTriState($filters['low_stock'] ?? null) === true) {
            $query->lowStock();
        }

        return $this->applyCatalogItemFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyCatalogItemFilters(Builder $query, array $filters): Builder
    {
        [$saleMin, $saleMax] = FilterRangeParser::parseNumericRange(
            isset($filters['price_range']) ? (string) $filters['price_range'] : null,
        );
        if ($saleMin !== null || $saleMax !== null) {
            $query = $query->byPrice($saleMin, $saleMax);
        }

        [$costMin, $costMax] = FilterRangeParser::parseNumericRange(
            isset($filters['cost_price_range']) ? (string) $filters['cost_price_range'] : null,
        );
        if ($costMin !== null || $costMax !== null) {
            $query = $query->byCostPrice($costMin, $costMax);
        }

        [$stockMin, $stockMax] = FilterRangeParser::parseIntBounds(
            $filters['stock_min'] ?? null,
            $filters['stock_max'] ?? null,
        );
        if ($stockMin !== null || $stockMax !== null) {
            $query = $query->byStockRange($stockMin, $stockMax);
        }

        if (! empty($filters['item_status'])) {
            $query = $query->byItemStatus((string) $filters['item_status']);
        }

        if (! empty($filters['size'])) {
            $query = $query->bySize((string) $filters['size']);
        }

        if (! empty($filters['color'])) {
            $query = $query->byColor((string) $filters['color']);
        }

        $universal = FilterRangeParser::parseBooleanTriState($filters['universal'] ?? null);
        if ($universal !== null) {
            $query = $query->byUniversalFlag($universal);
        }

        [$discountMin, $discountMax] = FilterRangeParser::parseFloatBounds(
            $filters['max_discount_min'] ?? null,
            $filters['max_discount_max'] ?? null,
        );
        if ($discountMin !== null || $discountMax !== null) {
            $query = $query->byMaxDiscount($discountMin, $discountMax);
        }

        [$profitMin, $profitMax] = FilterRangeParser::parseFloatBounds(
            $filters['profit_min'] ?? null,
            $filters['profit_max'] ?? null,
        );
        if ($profitMin !== null || $profitMax !== null) {
            $query = $query->byProfitRange($profitMin, $profitMax);
        }

        [$profitPercentMin, $profitPercentMax] = FilterRangeParser::parseFloatBounds(
            $filters['profit_percent_min'] ?? null,
            $filters['profit_percent_max'] ?? null,
        );
        if ($profitPercentMin !== null || $profitPercentMax !== null) {
            $query = $query->byProfitPercentRange($profitPercentMin, $profitPercentMax);
        }

        if (! empty($filters['stock_alert_level']) && $filters['stock_alert_level'] !== 'all') {
            $query = $query->byStockAlertLevel((string) $filters['stock_alert_level']);
        }

        return $query;
    }
}
