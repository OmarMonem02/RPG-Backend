<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $types = $this->query('type', []);
        if (is_string($types) && $types !== '') {
            $types = array_map('trim', explode(',', $types));
        }

        $this->merge([
            'type' => is_array($types) ? array_values(array_filter($types, fn ($type) => $type !== '')) : [],
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'array'],
            'type.*' => ['string', Rule::in(['product', 'spare_part', 'bike', 'maintenance_service'])],
            'search' => ['nullable', 'string'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer'],
            'sector_id' => ['nullable', 'integer', 'exists:maintenance_service_sectors,id'],
            'currency' => ['nullable', Rule::in(['EGP', 'USD', 'egp', 'usd'])],
            'price_min' => ['nullable', 'numeric'],
            'price_max' => ['nullable', 'numeric', 'gte:price_min'],
            'status' => ['nullable', 'string'],
            'bike_blueprint_id' => ['nullable', 'integer', 'exists:bike_blueprints,id'],
            'compatible_with_blueprint_id' => ['nullable', 'integer', 'exists:bike_blueprints,id'],
            'in_stock_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
