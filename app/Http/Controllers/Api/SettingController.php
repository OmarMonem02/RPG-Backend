<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use App\Services\CatalogPricingService;
use App\Services\ExchangeRatePricingService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(
        private readonly CatalogPricingService $catalogPricing,
        private readonly ExchangeRatePricingService $exchangeRatePricing,
    ) {}
    private function formatSettingsPayload(): array
    {
        $settings = Setting::query()
            ->whereIn('key', ['tax_rate', 'exchange_rate', 'exchange_rate_eur'])
            ->pluck('value', 'key');

        return [
            'tax_rate' => $settings->has('tax_rate') ? (float) $settings->get('tax_rate') : null,
            'exchange_rate' => $settings->has('exchange_rate') ? (float) $settings->get('exchange_rate') : null,
            'exchange_rate_eur' => $settings->has('exchange_rate_eur') ? (float) $settings->get('exchange_rate_eur') : null,
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json($this->formatSettingsPayload());
    }

    public function update(SettingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $oldRates = $this->catalogPricing->exchangeRates();
        $rateKeys = ['exchange_rate', 'exchange_rate_eur'];
        $ratesChanging = collect($rateKeys)->contains(fn (string $key) => array_key_exists($key, $validated));

        foreach ($validated as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value]
            );
        }

        $payload = $this->formatSettingsPayload();

        if ($ratesChanging) {
            $newRates = $this->catalogPricing->exchangeRates();
            $payload['pricing_impact'] = $this->exchangeRatePricing->applyRateChange($newRates);
            $payload['pricing_impact']['rates_changed'] = [
                'usd' => ['from' => $oldRates['usd'], 'to' => $newRates['usd']],
                'eur' => ['from' => $oldRates['eur'], 'to' => $newRates['eur']],
            ];
        }

        return response()->json($payload);
    }
}
