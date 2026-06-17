<?php

namespace App\Http\Requests;

use App\Support\CatalogItemAttributeRules;
use App\Support\CatalogPricingRules;
use App\Support\InventoryImageRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenancePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('maintenance_part');
        if (is_object($id)) {
            $id = $id->id;
        }

        return [
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:255', Rule::unique('maintenance_parts', 'sku')->ignore($id)],
            'part_number' => ['nullable', 'string', 'max:255', Rule::unique('maintenance_parts', 'part_number')->ignore($id)],
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_alarm' => 'required|integer|min:0',
            'maintenance_parts_category_id' => 'required|integer|exists:maintenance_part_categories,id',
            'cost_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            ...CatalogPricingRules::fieldRules(false),
            'brand_id' => 'required|integer|exists:brands,id',
            'max_discount_type' => 'required|in:fixed,percentage',
            'max_discount_value' => 'required|numeric|min:0',
            'universal' => 'boolean',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'bike_blueprint_ids' => 'array|nullable',
            'bike_blueprint_ids.*' => 'integer|exists:bike_blueprints,id',
            ...CatalogItemAttributeRules::fieldRules(),
            ...InventoryImageRules::fieldRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU already exists.',
            'part_number.unique' => 'This Part Number already exists.',
            'maintenance_parts_category_id.exists' => 'Selected category does not exist.',
            'brand_id.exists' => 'Selected brand does not exist.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            CatalogPricingRules::validateMarginMode($validator);
            InventoryImageRules::validatePrimarySelection($validator);

            if (($this->input('sale_price_mode') ?? 'manual') === 'manual' && ! $this->filled('sale_price')) {
                $validator->errors()->add('sale_price', 'Sale price is required for manual sale pricing.');
            }

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
