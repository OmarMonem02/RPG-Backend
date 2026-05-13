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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string'],
            'customer_phone' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'type' => ['nullable', Rule::in(['site', 'online', 'delivery'])],
            'status' => ['nullable', Rule::in(['completed', 'partial', 'pending'])],
            'delivery_status' => ['nullable', Rule::in(['pending', 'in-transit', 'delivered'])],
            'is_maintenance' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'total_min' => ['nullable', 'numeric', 'min:0'],
            'total_max' => ['nullable', 'numeric', 'gte:total_min'],
            'item_type' => ['nullable', Rule::in(['product', 'spare_part', 'maintenance_service', 'bike'])],
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'highest', 'lowest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
