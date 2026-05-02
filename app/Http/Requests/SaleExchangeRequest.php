<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSellablePayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaleExchangeRequest extends FormRequest
{
    use ValidatesSellablePayload;

    protected function prepareForValidation(): void
    {
        if ($this->has('replacement') && ! $this->has('replacements')) {
            $replacement = $this->input('replacement');
            $normalized = is_array($replacement) ? [$replacement + ['qty' => $replacement['qty'] ?? 1]] : [];
            $this->merge(['replacements' => $normalized]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'replacements' => ['required', 'array', 'min:1'],
            'replacements.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'replacements.*.spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'replacements.*.maintenance_service_id' => ['nullable', 'integer', 'exists:maintenance_services,id'],
            'replacements.*.bike_for_sale_id' => ['nullable', 'integer', 'exists:bike_for_sale,id'],
            'replacements.*.selling_price' => ['required', 'numeric', 'min:0'],
            'replacements.*.discount' => ['nullable', 'numeric', 'min:0'],
            'replacements.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $replacements = $this->input('replacements', []);
                foreach ($replacements as $index => $replacement) {
                    $this->validateSingleSellableReference($validator, $replacement, "replacements.{$index}");
                }
            },
        ];
    }
}
