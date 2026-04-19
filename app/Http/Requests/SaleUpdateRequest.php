<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'payment_method_id' => ['sometimes', 'integer', 'exists:payment_methods,id'],
            'type' => ['sometimes', Rule::in(['site', 'online', 'delivery'])],
            'status' => ['sometimes', Rule::in(['completed', 'partial', 'pending'])],
            'delivery_status' => ['nullable', 'string'],
            'shipping_fee' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
