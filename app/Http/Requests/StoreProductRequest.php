<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([Product::TYPE_PART, Product::TYPE_ACCESSORY])],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'part_number' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'qty' => ['required', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'cost_price_usd' => ['nullable', 'numeric', 'min:0'],
            'max_discount_type' => ['required', 'string', Rule::in([
                Product::DISCOUNT_TYPE_PERCENTAGE,
                Product::DISCOUNT_TYPE_FIXED,
            ])],
            'max_discount_value' => ['required', 'numeric', 'min:0'],
            'is_universal' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'bike_ids' => ['nullable', 'array'],
            'bike_ids.*' => ['integer', 'exists:bikes,id'],
            'units' => ['nullable', 'array'],
            'units.*.unit_name' => ['required_with:units', 'string', 'max:255'],
            'units.*.conversion_factor' => ['required_with:units', 'numeric', 'gt:0'],
            'units.*.price' => ['required_with:units', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $units = $this->input('units', []);

            if ($units !== [] && ! collect($units)->contains(fn (array $unit) => (float) ($unit['conversion_factor'] ?? 0) === 1.0)) {
                $validator->errors()->add('units', 'At least one product unit must have a conversion factor of 1.');
            }

            $type = $this->input('max_discount_type');
            $value = (float) $this->input('max_discount_value', 0);
            $sellingPrice = (float) $this->input('selling_price', 0);

            if ($type === Product::DISCOUNT_TYPE_PERCENTAGE && $value > 100) {
                $validator->errors()->add('max_discount_value', 'Percentage discount cannot exceed 100.');
            }

            if ($type === Product::DISCOUNT_TYPE_FIXED && $value > $sellingPrice) {
                $validator->errors()->add('max_discount_value', 'Fixed discount cannot exceed the selling price.');
            }
        });
    }
}
