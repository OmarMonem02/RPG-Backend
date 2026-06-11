<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PricingAlarmsApplyRequest;
use App\Http\Requests\PricingAlarmsPreviewRequest;
use App\Services\ExchangeRatePricingService;
use Illuminate\Http\JsonResponse;

class PricingAlarmsController extends Controller
{
    public function __construct(
        private readonly ExchangeRatePricingService $exchangeRatePricing,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'items' => $this->exchangeRatePricing->listPricingAlarms(),
        ]);
    }

    public function preview(PricingAlarmsPreviewRequest $request): JsonResponse
    {
        $rates = $this->exchangeRatePricing->ratesFromSettings();
        $all = $this->exchangeRatePricing->listPricingAlarms($rates);
        $ids = collect($request->validated('items', []))->keyBy(fn (array $item) => $item['item_type'].':'.$item['id']);

        $items = $ids->isEmpty()
            ? $all
            : array_values(array_filter(
                $all,
                fn (array $row) => $ids->has($row['item_type'].':'.$row['id']),
            ));

        return response()->json(['items' => $items]);
    }

    public function apply(PricingAlarmsApplyRequest $request): JsonResponse
    {
        $result = $this->exchangeRatePricing->applySuggestedPrices(
            $request->validated('items'),
        );

        return response()->json($result);
    }

    public function previewRateChange(PricingAlarmsPreviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $current = $this->exchangeRatePricing->ratesFromSettings();

        $newRates = [
            'usd' => isset($validated['exchange_rate']) ? (float) $validated['exchange_rate'] : $current['usd'],
            'eur' => isset($validated['exchange_rate_eur']) ? (float) $validated['exchange_rate_eur'] : $current['eur'],
        ];

        return response()->json(
            $this->exchangeRatePricing->previewRateChange($current, $newRates),
        );
    }
}
