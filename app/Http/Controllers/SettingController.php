<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExchangeRateRequest;
use App\Http\Requests\UpdateSettingRequest;
use App\Services\Inventory\UpdateExchangeRateService;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly UpdateExchangeRateService $updateExchangeRateService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Settings retrieved successfully.',
            'data' => $this->settingService->all(),
        ]);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $setting = $this->settingService->update(
            $request->validated()['key'],
            $request->validated()['value'] ?? null
        );

        return response()->json([
            'message' => 'Setting updated successfully.',
            'data' => [
                'key' => $setting->key,
                'value' => $this->settingService->get($setting->key),
            ],
        ]);
    }

    public function updateExchangeRate(UpdateExchangeRateRequest $request): JsonResponse
    {
        $exchangeRate = $this->updateExchangeRateService->execute(
            (float) $request->validated()['rate'],
            $request->validated()['currency'] ?? 'USD'
        );

        return response()->json([
            'message' => 'Exchange rate updated successfully.',
            'data' => $exchangeRate,
        ]);
    }
}
