<?php

namespace App\Services\Inventory;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateExchangeRateService
{
    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    public function execute(float $rate, string $currency = ExchangeRate::USD): ExchangeRate
    {
        return DB::transaction(function () use ($rate, $currency): ExchangeRate {
            $exchangeRate = ExchangeRate::query()->create([
                'currency' => $currency,
                'value' => $rate,
                'rate' => $rate,
                'updated_by' => Auth::id(),
            ]);

            $this->settingService->update('exchange_rate', $rate);

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
