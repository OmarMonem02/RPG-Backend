<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_rate' => ['sometimes', 'numeric', 'min:0'],
            'exchange_rate' => ['sometimes', 'numeric', 'gt:0'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (empty($this->validatedKeys())) {
                    $validator->errors()->add('settings', 'At least one setting must be provided.');
                }
            },
        ];
    }

    private function validatedKeys(): array
    {
        return array_filter(
            ['tax_rate', 'exchange_rate'],
            fn(string $key): bool => $this->has($key)
        );
    }
}
