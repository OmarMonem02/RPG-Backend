<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSellablePayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TicketItemRequest extends FormRequest
{
    use ValidatesSellablePayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $qtyRule = $this->isMethod('POST') ? 'required' : 'sometimes';

        return array_merge([
            'product_id' => ['nullable', 'exists:products,id'],
            'spare_part_id' => ['nullable', 'exists:spare_parts,id'],
            'maintenance_part_id' => ['nullable', 'exists:maintenance_parts,id'],
            'maintenance_service_id' => ['nullable', 'exists:maintenance_services,id'],
            'price_snapshot' => ['nullable', 'numeric'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_approval_request_id' => ['nullable', 'integer', 'exists:approval_requests,id'],
            'qty' => [$qtyRule, 'integer', 'min:1'],
        ], $this->unstoredFieldRules(requireSalePrice: false));
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->isMethod('POST')) {
                    $this->validateLineItemReference($validator, $this->all());
                }
            },
        ];
    }
}
