<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSaleDiscountAdminPassword;
use App\Models\CustomerAddress;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaleUpdateRequest extends FormRequest
{
    use ValidatesSaleDiscountAdminPassword;
    protected function prepareForValidation(): void
    {
        if ($this->has('sale_discount') && ! $this->has('discount')) {
            $this->merge(['discount' => $this->input('sale_discount')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'customer_address_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_addresses,id'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'payment_method_id' => ['sometimes', 'integer', 'exists:payment_methods,id'],
            'type' => ['sometimes', Rule::in(['site', 'online', 'delivery'])],
            'delivery_status' => ['nullable', Rule::in(['pending', 'in-transit', 'delivered'])],
            'shipping_fee' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'admin_password' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateSaleDiscountAdminPassword($validator);
            },
            function (Validator $validator): void {
                if (! $this->has('customer_address_id')) {
                    return;
                }

                $addressId = $this->input('customer_address_id');
                if (! $addressId) {
                    return;
                }

                /** @var Sale|null $sale */
                $sale = $this->route('sale');
                $saleType = $this->input('type', $sale?->type);
                if (! in_array($saleType, ['online', 'delivery'], true)) {
                    return;
                }

                $customerId = (int) ($this->input('customer_id') ?? $sale?->customer_id);
                $address = CustomerAddress::query()->find($addressId);
                if ($address && $customerId > 0 && (int) $address->customer_id !== $customerId) {
                    $validator->errors()->add(
                        'customer_address_id',
                        'The selected address does not belong to this customer.',
                    );
                }
            },
        ];
    }
}
