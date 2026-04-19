<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSellablePayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaleItemStoreRequest extends FormRequest
{
    use ValidatesSellablePayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'maintenance_service_id' => ['nullable', 'integer', 'exists:maintenance_services,id'],
            'bike_for_sale_id' => ['nullable', 'integer', 'exists:bike_for_sale,id'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'qty' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateSingleSellableReference($validator, $this->all());
            },
        ];
    }
}
