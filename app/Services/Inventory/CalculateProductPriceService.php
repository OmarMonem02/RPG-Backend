<?php

namespace App\Services\Inventory;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Validation\ValidationException;

class CalculateProductPriceService
{
    public function execute(Product $product, ?int $unitId = null): array
    {
        $unit = null;

        if ($unitId !== null) {
            $unit = ProductUnit::query()->where('product_id', $product->id)->find($unitId);

            if ($unit === null) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Selected unit does not belong to the product.',
                ]);
            }
        }

        $exchangeRate = (float) ExchangeRate::query()
            ->where('currency', ExchangeRate::USD)
            ->value('rate') ?: 1.0;

        $sellingPrice = (float) ($unit?->price ?? $product->selling_price);
        $costPriceEgp = $product->cost_price_usd !== null
            ? round((float) $product->cost_price_usd * $exchangeRate, 2)
            : round((float) $product->cost_price, 2);

        return [
            'exchange_rate' => round($exchangeRate, 4),
            'unit' => $unit?->unit_name,
            'selling_price' => round($sellingPrice, 2),
            'cost_price_egp' => $costPriceEgp,
            'cost_price_usd' => $product->cost_price_usd !== null ? round((float) $product->cost_price_usd, 2) : null,
        ];
    }
}
