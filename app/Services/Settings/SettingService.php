<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingService
{
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->value('value');

        if ($setting === null) {
            return $default;
        }

        $decoded = json_decode($setting, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting;
    }

    public function updateSetting(string $key, mixed $value): Setting
    {
        return $this->update($key, $value);
    }

    public function update(string $key, mixed $value): Setting
    {
        return DB::transaction(function () use ($key, $value): Setting {
            return Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value]
            );
        });
    }

    public function all(): array
    {
        return Setting::query()
            ->orderBy('key')
            ->get()
            ->map(fn (Setting $setting) => [
                'key' => $setting->key,
                'value' => $this->get($setting->key),
            ])
            ->all();
    }
}
