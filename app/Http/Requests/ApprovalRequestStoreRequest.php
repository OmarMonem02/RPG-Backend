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

        if ($user === null || $user->role === User::ROLE_ADMIN) {
            return false;
        }

        $type = $this->input('type');

        if (in_array($type, [
            ApprovalRequest::TYPE_SALE_DISCOUNT,
            ApprovalRequest::TYPE_SALE_ITEM_DISCOUNT,
        ], true)) {
            return $user->hasPermission('sales', 'create');
        }

        if (in_array($type, [
            ApprovalRequest::TYPE_TICKET_DISCOUNT,
            ApprovalRequest::TYPE_TICKET_ITEM_DISCOUNT,
        ], true)) {
            return $user->hasPermission('maintenance', 'update')
                || $user->hasPermission('sales', 'create');
        }

        return false;
    }

    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'type' => ['required', 'string', Rule::in([
                ApprovalRequest::TYPE_SALE_DISCOUNT,
                ApprovalRequest::TYPE_TICKET_DISCOUNT,
                ApprovalRequest::TYPE_SALE_ITEM_DISCOUNT,
                ApprovalRequest::TYPE_TICKET_ITEM_DISCOUNT,
            ])],
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
        ];

        if ($type === ApprovalRequest::TYPE_SALE_DISCOUNT) {
            $rules['payload.sale_context'] = ['required', 'array'];
            $rules['payload.sale_context.customer_id'] = ['nullable', 'integer'];
            $rules['payload.sale_context.customer_name'] = ['nullable', 'string'];
            $rules['payload.sale_context.seller_id'] = ['nullable', 'integer'];
            $rules['payload.sale_context.sale_type'] = ['nullable', 'string'];
            $rules['payload.sale_context.shipping_fee'] = ['nullable', 'numeric', 'min:0'];
            $rules['payload.sale_context.is_maintenance'] = ['nullable', 'boolean'];
            $rules['payload.sale_context.discount_includes_maintenance'] = ['nullable', 'boolean'];
            $rules['payload.sale_context.discount_scope'] = ['nullable', 'array'];
            $rules['payload.sale_context.discount_scope.spare_parts'] = ['nullable', 'boolean'];
            $rules['payload.sale_context.discount_scope.products'] = ['nullable', 'boolean'];
            $rules['payload.sale_context.discount_scope.maintenance_services'] = ['nullable', 'boolean'];
            $rules['payload.sale_context.discount_scope.bikes'] = ['nullable', 'boolean'];
            $rules['payload.sale_context.full_cart_subtotal'] = ['nullable', 'numeric', 'min:0'];
        }

        if ($type === ApprovalRequest::TYPE_TICKET_DISCOUNT) {
            $rules['payload.ticket_context'] = ['required', 'array'];
            $rules['payload.ticket_context.ticket_id'] = ['required', 'integer', 'min:1'];
            $rules['payload.ticket_context.customer_name'] = ['nullable', 'string'];
            $rules['payload.ticket_context.discount_includes_maintenance'] = ['nullable', 'boolean'];
            $rules['payload.ticket_context.discount_scope'] = ['nullable', 'array'];
            $rules['payload.ticket_context.discount_scope.spare_parts'] = ['nullable', 'boolean'];
            $rules['payload.ticket_context.discount_scope.products'] = ['nullable', 'boolean'];
            $rules['payload.ticket_context.discount_scope.maintenance_services'] = ['nullable', 'boolean'];
            $rules['payload.ticket_context.full_cart_subtotal'] = ['nullable', 'numeric', 'min:0'];
        }

        if (in_array($type, [
            ApprovalRequest::TYPE_SALE_ITEM_DISCOUNT,
            ApprovalRequest::TYPE_TICKET_ITEM_DISCOUNT,
        ], true)) {
            $rules['payload.item_context'] = ['required', 'array'];
            $rules['payload.item_context.sellable_type'] = ['required', 'string'];
            $rules['payload.item_context.sellable_id'] = ['required', 'integer', 'min:1'];
            $rules['payload.item_context.item_name'] = ['required', 'string'];
            $rules['payload.item_context.unit_price'] = ['required', 'numeric', 'min:0'];
            $rules['payload.item_context.quantity'] = ['required', 'numeric', 'min:1'];
            $rules['payload.item_context.currency'] = ['required', 'string'];
            $rules['payload.item_context.catalog_max_discount_type'] = ['nullable', 'string'];
            $rules['payload.item_context.catalog_max_discount_value'] = ['nullable', 'numeric', 'min:0'];
            $rules['payload.item_context.cost_price'] = ['nullable', 'numeric', 'min:0'];
            $rules['payload.item_context.cost_currency'] = ['nullable', 'string', 'in:EGP,USD,EUR'];
        }

        if ($type === ApprovalRequest::TYPE_SALE_ITEM_DISCOUNT) {
            $rules['payload.sale_context'] = ['nullable', 'array'];
            $rules['payload.sale_context.customer_id'] = ['nullable', 'integer'];
            $rules['payload.sale_context.customer_name'] = ['nullable', 'string'];
            $rules['payload.sale_context.seller_id'] = ['nullable', 'integer'];
            $rules['payload.sale_context.sale_type'] = ['nullable', 'string'];
        }

        if ($type === ApprovalRequest::TYPE_TICKET_ITEM_DISCOUNT) {
            $rules['payload.ticket_context'] = ['required', 'array'];
            $rules['payload.ticket_context.ticket_id'] = ['required', 'integer', 'min:1'];
            $rules['payload.ticket_context.customer_name'] = ['nullable', 'string'];
            $rules['payload.item_context.ticket_id'] = ['required', 'integer', 'min:1'];
            $rules['payload.item_context.task_id'] = ['required', 'integer', 'min:1'];
            $rules['payload.item_context.ticket_item_id'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $amount = (float) $this->input('requested_discount_amount', 0);
                $subtotal = (float) $this->input('cart_subtotal', 0);
                $type = $this->input('type');

                $label = in_array($type, [
                    ApprovalRequest::TYPE_SALE_ITEM_DISCOUNT,
                    ApprovalRequest::TYPE_TICKET_ITEM_DISCOUNT,
                ], true)
                    ? 'line subtotal'
                    : 'cart subtotal';

                if ($amount > $subtotal) {
                    $validator->errors()->add(
                        'requested_discount_amount',
                        "Requested discount cannot exceed the {$label}.",
                    );
                }
            },
        ];
    }
}
