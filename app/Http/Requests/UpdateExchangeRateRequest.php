<?php

namespace App\Http\Requests;

use App\Models\ExchangeRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', Rule::in([ExchangeRate::USD])],
            'rate' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
