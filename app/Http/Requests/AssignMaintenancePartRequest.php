<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignMaintenancePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_part_id' => 'required_without_all:maintenance_part_ids,maintenance_part_data|integer|exists:maintenance_parts,id',
            'maintenance_part_ids' => 'required_without_all:maintenance_part_id,maintenance_part_data|array',
            'maintenance_part_ids.*' => 'integer|exists:maintenance_parts,id',
            'maintenance_part_data' => 'required_without_all:maintenance_part_id,maintenance_part_ids|array',
            'maintenance_part_data.name' => 'required_with:maintenance_part_data|string|max:255',
            'maintenance_part_data.sku' => 'required_with:maintenance_part_data|string|max:255|unique:maintenance_parts,sku',
            'maintenance_part_data.part_number' => 'nullable|string|max:255|unique:maintenance_parts,part_number',
            'maintenance_part_data.stock_quantity' => 'nullable|integer|min:0',
            'maintenance_part_data.low_stock_alarm' => 'nullable|integer|min:0',
            'maintenance_part_data.maintenance_parts_category_id' => 'required_with:maintenance_part_data|integer|exists:maintenance_part_categories,id',
            'maintenance_part_data.brand_id' => 'required_with:maintenance_part_data|integer|exists:brands,id',
            'maintenance_part_data.sale_currency' => ['required_with:maintenance_part_data', Rule::in(config('currencies.supported'))],
            'maintenance_part_data.cost_price' => 'required_with:maintenance_part_data|numeric|min:0',
            'maintenance_part_data.sale_price' => 'required_with:maintenance_part_data|numeric|min:0',
            'maintenance_part_data.max_discount_type' => 'required_with:maintenance_part_data|in:fixed,percentage',
            'maintenance_part_data.max_discount_value' => 'nullable|numeric|min:0',
            'maintenance_part_data.universal' => 'nullable|boolean',
            'maintenance_part_data.notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'maintenance_part_id.exists' => 'Maintenance part does not exist.',
            'maintenance_part_ids.*.exists' => 'One or more maintenance parts do not exist.',
        ];
    }
}
