<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'type' => ['sometimes', 'required', 'string', Rule::in([Product::TYPE_PART, Product::TYPE_ACCESSORY])],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($productId)],
            'part_number' => ['nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'required', 'integer', 'exists:brands,id'],
            'qty' => ['sometimes', 'required', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_price_usd' => ['nullable', 'numeric', 'min:0'],
            'max_discount_type' => ['sometimes', 'required', 'string', Rule::in([
                Product::DISCOUNT_TYPE_PERCENTAGE,
                Product::DISCOUNT_TYPE_FIXED,
            ])],
            'max_discount_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_universal' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'bike_ids' => ['nullable', 'array'],
            'bike_ids.*' => ['integer', 'exists:bikes,id'],
            'units' => ['nullable', 'array'],
            'units.*.id' => ['nullable', 'integer', 'exists:product_units,id'],
            'units.*.unit_name' => ['required_with:units', 'string', 'max:255'],
            'units.*.conversion_factor' => ['required_with:units', 'numeric', 'gt:0'],
            'units.*.price' => ['required_with:units', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $units = $this->input('units');

            if (is_array($units) && $units !== [] && ! collect($units)->contains(fn (array $unit) => (float) ($unit['conversion_factor'] ?? 0) === 1.0)) {
                $validator->errors()->add('units', 'At least one product unit must have a conversion factor of 1.');
            }

            $product = $this->route('product');
            $type = $this->input('max_discount_type', $product?->max_discount_type);
            $value = (float) $this->input('max_discount_value', $product?->max_discount_value);
            $sellingPrice = (float) $this->input('selling_price', $product?->selling_price);

            if ($type === Product::DISCOUNT_TYPE_PERCENTAGE && $value > 100) {
                $validator->errors()->add('max_discount_value', 'Percentage discount cannot exceed 100.');
            }

            if ($type === Product::DISCOUNT_TYPE_FIXED && $value > $sellingPrice) {
                $validator->errors()->add('max_discount_value', 'Fixed discount cannot exceed the selling price.');
            }

            $productType = $this->input('type', $product?->type);
            $categoryId = $this->input('category_id', $product?->category_id);
            $category = Category::query()->find($categoryId);

            if ($category !== null && $category->type !== $productType) {
                $validator->errors()->add('category_id', 'Product category type must match the selected product type.');
            }
        });
    }
}
