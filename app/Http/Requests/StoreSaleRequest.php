<?php

namespace App\Http\Requests;

use App\Models\Sale;
use App\Models\Payment;
use App\Models\SaleItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id', 'required_without:customer'],
            'customer' => ['nullable', 'array', 'required_without:customer_id'],
            'customer.name' => ['required_with:customer', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.address' => ['nullable', 'string'],
            'seller_id' => [
                'nullable',
                'integer',
                Rule::exists('sellers', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'type' => ['required', 'string', Rule::in([
                Sale::TYPE_GARAGE,
                Sale::TYPE_DELIVERY,
                Sale::TYPE_ONLINE,
            ])],
            'items' => ['nullable', 'array'],
            'items.*.item_type' => ['required_with:items', 'string', Rule::in([
                SaleItem::ITEM_TYPE_PRODUCT,
                SaleItem::ITEM_TYPE_BIKE,
            ])],
            'items.*.item_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:1'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.method' => ['required_with:payments', 'string', Rule::in([
                Payment::METHOD_CASH,
                Payment::METHOD_VISA,
                Payment::METHOD_INSTAPAY,
            ])],
            'payments.*.status' => ['nullable', 'string', Rule::in([
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PENDING,
            ])],
            'complete_now' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                foreach ($this->input('items', []) as $index => $item) {
                    if (($item['item_type'] ?? null) === SaleItem::ITEM_TYPE_BIKE && (int) ($item['qty'] ?? 0) !== 1) {
                        $validator->errors()->add("items.{$index}.qty", 'Bike quantity must be exactly 1.');
                    }
                }
            },
        ];
    }
}
