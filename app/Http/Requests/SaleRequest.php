<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSellablePayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaleRequest extends FormRequest
{
    use ValidatesSellablePayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'seller_id' => ['nullable', 'exists:sellers,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'type' => ['required', Rule::in(['site', 'online', 'delivery'])],
            'status' => ['required', Rule::in(['completed', 'partial', 'pending'])],
            'delivery_status' => ['nullable', 'string'],
            'shipping_fee' => ['nullable', 'numeric'],
            'discount' => ['nullable', 'numeric'],
            'is_maintenance' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.spare_part_id' => ['nullable', 'exists:spare_parts,id'],
            'items.*.maintenance_service_id' => ['nullable', 'exists:maintenance_services,id'],
            'items.*.bike_for_sale_id' => ['nullable', 'exists:bike_for_sale,id'],
            'items.*.selling_price' => ['required', 'numeric'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('items', []) as $index => $item) {
                    $this->validateSingleSellableReference($validator, $item, "items.{$index}");
                }
            },
        ];
    }
}
