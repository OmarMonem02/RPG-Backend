<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SparePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('spare_part');
        if (is_object($id)) {
            $id = $id->id;
        }

        return [
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:255', Rule::unique('spare_parts', 'sku')->ignore($id)],
            'image' => 'nullable|string|url',
            'image_public_id' => ['nullable', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:255', Rule::unique('spare_parts', 'part_number')->ignore($id)],
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_alarm' => 'required|integer|min:0',
            'spare_parts_category_id' => 'required|integer|exists:spare_part_categories,id',
            'currency_pricing' => ['required', Rule::in(config('currencies.supported'))],
            'cost_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'brand_id' => 'required|integer|exists:brands,id',
            'max_discount_type' => 'required|in:fixed,percentage',
            'max_discount_value' => 'required|numeric|min:0',
            'universal' => 'boolean',
            'notes' => 'nullable|string',
            'bike_blueprint_ids' => 'array|nullable',
            'bike_blueprint_ids.*' => 'integer|exists:bike_blueprints,id',
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU already exists.',
            'part_number.unique' => 'This Part Number already exists.',
            'spare_parts_category_id.exists' => 'Selected category does not exist.',
            'brand_id.exists' => 'Selected brand does not exist.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            // Only enforce when client explicitly disables Universal Part
            if (! $this->has('universal') || $this->boolean('universal')) {
                return;
            }
            $ids = $this->input('bike_blueprint_ids');
            if (! is_array($ids) || count(array_filter($ids)) < 1) {
                $validator->errors()->add(
                    'bike_blueprint_ids',
                    'Select at least one compatible bike blueprint when Universal Part is disabled.'
                );
            }
        });
    }
}
