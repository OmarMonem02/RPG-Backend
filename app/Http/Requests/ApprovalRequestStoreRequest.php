<?php

namespace App\Http\Requests;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role !== User::ROLE_ADMIN
            && $user->hasPermission('sales', 'create');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([ApprovalRequest::TYPE_SALE_DISCOUNT])],
            'requested_discount_amount' => ['required', 'numeric', 'min:0.01'],
            'discount_input_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_input_value' => ['required', 'numeric', 'min:0.01'],
            'cart_subtotal' => ['required', 'numeric', 'min:0.01'],
            'payload' => ['required', 'array'],
            'payload.cart_items' => ['required', 'array', 'min:1'],
            'payload.cart_items.*.sellable_type' => ['required', 'string'],
            'payload.cart_items.*.sellable_id' => ['required', 'integer', 'min:1'],
            'payload.cart_items.*.item_name' => ['required', 'string'],
            'payload.cart_items.*.selling_price' => ['required', 'numeric', 'min:0'],
            'payload.cart_items.*.discount_amount' => ['required', 'numeric', 'min:0'],
            'payload.cart_items.*.quantity' => ['required', 'numeric', 'min:1'],
            'payload.cart_items.*.currency' => ['required', 'string'],
            'payload.cart_items.*.line_total' => ['required', 'numeric', 'min:0'],
            'payload.sale_context' => ['required', 'array'],
            'payload.sale_context.customer_id' => ['nullable', 'integer'],
            'payload.sale_context.customer_name' => ['nullable', 'string'],
            'payload.sale_context.seller_id' => ['nullable', 'integer'],
            'payload.sale_context.sale_type' => ['nullable', 'string'],
            'payload.sale_context.shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'payload.sale_context.is_maintenance' => ['nullable', 'boolean'],
            'payload.sale_context.discount_includes_maintenance' => ['nullable', 'boolean'],
            'payload.sale_context.full_cart_subtotal' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $amount = (float) $this->input('requested_discount_amount', 0);
                $subtotal = (float) $this->input('cart_subtotal', 0);

                if ($amount > $subtotal) {
                    $validator->errors()->add(
                        'requested_discount_amount',
                        'Requested discount cannot exceed the cart subtotal.',
                    );
                }
            },
        ];
    }
}
