<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSaleDiscountAdminPassword;
use App\Http\Requests\Concerns\ValidatesSellablePayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaleRequest extends FormRequest
{
    use ValidatesSaleDiscountAdminPassword;
    use ValidatesSellablePayload;

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
            'customer_id' => ['required', 'exists:customers,id'],
            'seller_id' => ['nullable', 'exists:sellers,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'type' => ['required', Rule::in(['site', 'online', 'delivery'])],
            'status' => ['required', Rule::in(['completed', 'partial', 'pending'])],
            'delivery_status' => ['nullable', Rule::in(['pending', 'in-transit', 'delivered'])],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'admin_password' => ['nullable', 'string'],
            'is_maintenance' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.spare_part_id' => ['nullable', 'exists:spare_parts,id'],
            'items.*.maintenance_service_id' => ['nullable', 'exists:maintenance_services,id'],
            'items.*.bike_for_sale_id' => ['nullable', 'exists:bike_for_sale,id'],
            'items.*.selling_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $subtotal = 0.0;
                foreach ($this->input('items', []) as $index => $item) {
                    $this->validateSingleSellableReference($validator, $item, "items.{$index}");

                    $sellingPrice = $item['selling_price'] ?? null;
                    $discount = $item['discount'] ?? 0;
                    $qty = $item['qty'] ?? 1;
                    if (is_numeric($sellingPrice) && is_numeric($discount)) {
                        if ((float) $discount > (float) $sellingPrice) {
                            $validator->errors()->add("items.{$index}.discount", 'Item discount cannot exceed the selling price.');
                        }

                        $subtotal += max(0, (float) $sellingPrice - (float) $discount) * (is_numeric($qty) ? (int) $qty : 1);
                    }
                }

                $saleDiscount = $this->input('discount');
                if (is_numeric($saleDiscount) && (float) $saleDiscount > $subtotal) {
                    $validator->errors()->add('discount', 'Sale discount cannot exceed the items subtotal.');
                }

                $this->validateSaleDiscountAdminPassword($validator);
            },
        ];
    }
}
