<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PricingAlarmsApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'string', Rule::in(['product', 'spare_part', 'bike'])],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ];
    }
}
