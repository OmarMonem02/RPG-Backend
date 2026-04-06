<?php

namespace App\Services\Inventory;

use App\Models\ExchangeRate;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateExchangeRateService
{
    public function execute(float $rate, string $currency = ExchangeRate::USD): ExchangeRate
    {
        return DB::transaction(function () use ($rate, $currency): ExchangeRate {
            $exchangeRate = ExchangeRate::query()->updateOrCreate(
                ['currency' => $currency],
                ['rate' => $rate]
            );

            Product::query()
                ->whereNotNull('cost_price_usd')
                ->each(function (Product $product) use ($rate): void {
                    $product->update([
                        'cost_price' => round((float) $product->cost_price_usd * $rate, 2),
                    ]);
                });

            return $exchangeRate;
        });
    }
}
