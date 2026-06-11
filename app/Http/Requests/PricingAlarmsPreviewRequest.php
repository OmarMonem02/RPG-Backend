<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PricingAlarmsPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array'],
            'items.*.item_type' => ['required_with:items', 'string', Rule::in(['product', 'spare_part', 'bike'])],
            'items.*.id' => ['required_with:items', 'integer', 'min:1'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'exchange_rate_eur' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
