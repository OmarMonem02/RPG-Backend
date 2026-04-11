<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BikeBlueprintSparePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Single spare part assignment
            'spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],

            // Bulk spare parts assignment
            'spare_part_ids' => ['nullable', 'array'],
            'spare_part_ids.*' => ['integer', 'exists:spare_parts,id'],

            // Create and assign in one call
            'spare_part_data' => ['nullable', 'array'],
            'spare_part_data.name' => ['required_if:spare_part_data,array', 'string'],
            'spare_part_data.sku' => ['required_if:spare_part_data,array', 'string', 'unique:spare_parts,sku'],
            'spare_part_data.spare_parts_category_id' => ['required_if:spare_part_data,array', 'exists:spare_part_categories,id'],
            'spare_part_data.brand_id' => ['required_if:spare_part_data,array', 'exists:brands,id'],
            'spare_part_data.currency_pricing' => ['required_if:spare_part_data,array', 'in:EGP,USD'],
            'spare_part_data.cost_price' => ['required_if:spare_part_data,array', 'numeric', 'min:0'],
            'spare_part_data.sale_price' => ['required_if:spare_part_data,array', 'numeric', 'min:0'],
            'spare_part_data.max_discount_type' => ['required_if:spare_part_data,array', 'in:fixed,percentage'],
            'spare_part_data.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'spare_part_data.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'spare_part_id.required_without_all' => 'You must provide either spare_part_id, spare_part_ids, or spare_part_data.',
            'spare_part_ids.required_without_all' => 'You must provide either spare_part_id, spare_part_ids, or spare_part_data.',
            'spare_part_data.required_without_all' => 'You must provide either spare_part_id, spare_part_ids, or spare_part_data.',
            'spare_part_ids.*.exists' => 'One or more spare part IDs do not exist.',
            'spare_part_data.sku.unique' => 'The SKU has already been taken.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            // At least one of the three options must be provided
            if (empty($data['spare_part_id']) && empty($data['spare_part_ids']) && empty($data['spare_part_data'])) {
                $validator->errors()->add('spare_part_id', 'You must provide either spare_part_id, spare_part_ids, or spare_part_data.');
            }

            // Only one option should be provided at a time
            $providedCount = 0;
            if (! empty($data['spare_part_id'])) {
                $providedCount++;
            }
            if (! empty($data['spare_part_ids'])) {
                $providedCount++;
            }
            if (! empty($data['spare_part_data'])) {
                $providedCount++;
            }

            if ($providedCount > 1) {
                $validator->errors()->add('spare_part_id', 'Provide only one of: spare_part_id, spare_part_ids, or spare_part_data.');
            }
        });
    }
}
