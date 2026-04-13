<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    private function formatSettingsPayload(): array
    {
        $settings = Setting::query()
            ->whereIn('key', ['tax_rate', 'exchange_rate'])
            ->pluck('value', 'key');

        return [
            'tax_rate' => $settings->has('tax_rate') ? (float) $settings->get('tax_rate') : null,
            'exchange_rate' => $settings->has('exchange_rate') ? (float) $settings->get('exchange_rate') : null,
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json($this->formatSettingsPayload());
    }

    public function update(SettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value]
            );
        }

        return response()->json($this->formatSettingsPayload());
    }
}
