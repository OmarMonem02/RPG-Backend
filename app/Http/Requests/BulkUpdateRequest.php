<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'attributes' => ['required', 'array'],
            'attributes.selling_price' => ['sometimes', 'numeric', 'min:0'],
            'attributes.cost_price' => ['sometimes', 'numeric', 'min:0'],
            'attributes.cost_price_usd' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'attributes.max_discount_type' => ['sometimes', 'string', Rule::in([
                Product::DISCOUNT_TYPE_PERCENTAGE,
                Product::DISCOUNT_TYPE_FIXED,
            ])],
            'attributes.max_discount_value' => ['sometimes', 'numeric', 'min:0'],
            'attributes.is_universal' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $attributes = $this->input('attributes', []);

            if ($attributes === []) {
                $validator->errors()->add('attributes', 'At least one attribute must be provided for bulk update.');
            }

            $type = $attributes['max_discount_type'] ?? null;
            $value = (float) ($attributes['max_discount_value'] ?? 0);
            $sellingPrice = isset($attributes['selling_price']) ? (float) $attributes['selling_price'] : null;

            if ($type === Product::DISCOUNT_TYPE_PERCENTAGE && $value > 100) {
                $validator->errors()->add('attributes.max_discount_value', 'Percentage discount cannot exceed 100.');
            }

            if ($type === Product::DISCOUNT_TYPE_FIXED && $sellingPrice !== null && $value > $sellingPrice) {
                $validator->errors()->add('attributes.max_discount_value', 'Fixed discount cannot exceed the selling price.');
            }
        });
    }
}
