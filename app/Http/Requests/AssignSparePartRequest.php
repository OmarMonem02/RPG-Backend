<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignSparePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'spare_part_id' => 'required_without_all:spare_part_ids,spare_part_data|integer|exists:spare_parts,id',
            'spare_part_ids' => 'required_without_all:spare_part_id,spare_part_data|array',
            'spare_part_ids.*' => 'integer|exists:spare_parts,id',
            'spare_part_data' => 'required_without_all:spare_part_id,spare_part_ids|array',
            'spare_part_data.name' => 'required_with:spare_part_data|string|max:255',
            'spare_part_data.sku' => 'required_with:spare_part_data|string|max:255|unique:spare_parts,sku',
            'spare_part_data.image' => 'nullable|url',
            'spare_part_data.image_public_id' => 'nullable|string|max:255',
            'spare_part_data.part_number' => 'nullable|string|max:255|unique:spare_parts,part_number',
            'spare_part_data.stock_quantity' => 'nullable|integer|min:0',
            'spare_part_data.low_stock_alarm' => 'nullable|integer|min:0',
            'spare_part_data.spare_parts_category_id' => 'required_with:spare_part_data|integer|exists:spare_part_categories,id',
            'spare_part_data.brand_id' => 'required_with:spare_part_data|integer|exists:brands,id',
            'spare_part_data.currency_pricing' => 'required_with:spare_part_data|in:EGP,USD',
            'spare_part_data.cost_price' => 'required_with:spare_part_data|numeric|min:0',
            'spare_part_data.sale_price' => 'required_with:spare_part_data|numeric|min:0',
            'spare_part_data.max_discount_type' => 'required_with:spare_part_data|in:fixed,percentage',
            'spare_part_data.max_discount_value' => 'nullable|numeric|min:0',
            'spare_part_data.universal' => 'nullable|boolean',
            'spare_part_data.notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'spare_part_id.exists' => 'Spare part does not exist.',
            'spare_part_ids.*.exists' => 'One or more spare parts do not exist.',
        ];
    }
}
