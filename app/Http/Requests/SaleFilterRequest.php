<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],
            'customer_name' => ['nullable', 'string'],
            'customer_phone' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'type' => ['nullable', Rule::in(['site', 'online', 'delivery'])],
            'status' => ['nullable', Rule::in(['completed', 'partial', 'pending'])],
            'delivery_status' => ['nullable', 'string'],
            'is_maintenance' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'total_min' => ['nullable', 'numeric'],
            'total_max' => ['nullable', 'numeric', 'gte:total_min'],
            'item_type' => ['nullable', Rule::in(['product', 'spare_part', 'maintenance_service', 'bike'])],
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
