<?php

namespace App\Http\Controllers\Concerns;

use App\Support\FilterRangeParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesCatalogItemFilters
{
    /**
     * @return array{0: float|null, 1: float|null, 2: string|null}
     */
    protected function parsePriceRange(?string $priceRange): array
    {
        return FilterRangeParser::parseNumericRange($priceRange);
    }

    protected function applyCatalogItemFilters(Builder $query, Request $request): Builder
    {
        [$saleMin, $saleMax] = $this->parsePriceRange($request->query('price_range'));
        if ($saleMin !== null || $saleMax !== null) {
            $query = $query->byPrice($saleMin, $saleMax);
        }

        [$costMin, $costMax] = $this->parsePriceRange($request->query('cost_price_range'));
        if ($costMin !== null || $costMax !== null) {
            $query = $query->byCostPrice($costMin, $costMax);
        }

        [$stockMin, $stockMax] = FilterRangeParser::parseIntBounds(
            $request->query('stock_min'),
            $request->query('stock_max'),
        );
        if ($stockMin !== null || $stockMax !== null) {
            $query = $query->byStockRange($stockMin, $stockMax);
        }

        $itemStatus = $request->query('item_status');
        if ($itemStatus) {
            $query = $query->byItemStatus((string) $itemStatus);
        }

        $size = $request->query('size');
        if ($size) {
            $query = $query->bySize((string) $size);
        }

        $color = $request->query('color');
        if ($color) {
            $query = $query->byColor((string) $color);
        }

        $universal = FilterRangeParser::parseBooleanTriState($request->query('universal'));
        if ($universal !== null) {
            $query = $query->byUniversalFlag($universal);
        }

        [$discountMin, $discountMax] = FilterRangeParser::parseFloatBounds(
            $request->query('max_discount_min'),
            $request->query('max_discount_max'),
        );
        if ($discountMin !== null || $discountMax !== null) {
            $query = $query->byMaxDiscount($discountMin, $discountMax);
        }

        [$profitMin, $profitMax] = FilterRangeParser::parseFloatBounds(
            $request->query('profit_min'),
            $request->query('profit_max'),
        );
        if ($profitMin !== null || $profitMax !== null) {
            $query = $query->byProfitRange($profitMin, $profitMax);
        }

        [$profitPercentMin, $profitPercentMax] = FilterRangeParser::parseFloatBounds(
            $request->query('profit_percent_min'),
            $request->query('profit_percent_max'),
        );
        if ($profitPercentMin !== null || $profitPercentMax !== null) {
            $query = $query->byProfitPercentRange($profitPercentMin, $profitPercentMax);
        }

        $stockAlertLevel = $request->query('stock_alert_level');
        if ($stockAlertLevel) {
            $query = $query->byStockAlertLevel((string) $stockAlertLevel);
        }

        return $query;
    }

    protected function applyBikeForSaleFilters(Builder $query, Request $request): Builder
    {
        $brandId = $request->query('brand_id');
        if ($brandId) {
            $query = $query->byBlueprintBrand((int) $brandId);
        }

        [$mileageMin, $mileageMax] = FilterRangeParser::parseIntBounds(
            $request->query('mileage_min'),
            $request->query('mileage_max'),
        );
        if ($mileageMin !== null || $mileageMax !== null) {
            $query = $query->byMileage($mileageMin, $mileageMax);
        }

        [$costMin, $costMax] = $this->parsePriceRange($request->query('cost_price_range'));
        if ($costMin !== null || $costMax !== null) {
            $query = $query->byCostPrice($costMin, $costMax);
        }

        [$discountMin, $discountMax] = FilterRangeParser::parseFloatBounds(
            $request->query('max_discount_min'),
            $request->query('max_discount_max'),
        );
        if ($discountMin !== null || $discountMax !== null) {
            $query = $query->byMaxDiscount($discountMin, $discountMax);
        }

        [$profitMin, $profitMax] = FilterRangeParser::parseFloatBounds(
            $request->query('profit_min'),
            $request->query('profit_max'),
        );
        if ($profitMin !== null || $profitMax !== null) {
            $query = $query->byProfitRange($profitMin, $profitMax);
        }

        [$profitPercentMin, $profitPercentMax] = FilterRangeParser::parseFloatBounds(
            $request->query('profit_percent_min'),
            $request->query('profit_percent_max'),
        );
        if ($profitPercentMin !== null || $profitPercentMax !== null) {
            $query = $query->byProfitPercentRange($profitPercentMin, $profitPercentMax);
        }

        return $query;
    }

    protected function applyMaintenanceServiceFilters(Builder $query, Request $request): Builder
    {
        [$discountMin, $discountMax] = FilterRangeParser::parseFloatBounds(
            $request->query('max_discount_min'),
            $request->query('max_discount_max'),
        );
        if ($discountMin !== null || $discountMax !== null) {
            $query = $query->byMaxDiscount($discountMin, $discountMax);
        }

        $createdFrom = $request->query('created_from');
        if ($createdFrom) {
            $query = $query->whereDate('created_at', '>=', $createdFrom);
        }

        $createdTo = $request->query('created_to');
        if ($createdTo) {
            $query = $query->whereDate('created_at', '<=', $createdTo);
        }

        $haveCommission = FilterRangeParser::parseBooleanTriState($request->query('have_commission'));
        if ($haveCommission !== null) {
            $query = $query->where('have_commission', $haveCommission);
        }

        return $query;
    }
}
