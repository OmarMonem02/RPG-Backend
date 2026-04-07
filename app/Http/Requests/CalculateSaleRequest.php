<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Models\SaleItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CalculateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'string', Rule::in([
                SaleItem::ITEM_TYPE_PRODUCT,
                SaleItem::ITEM_TYPE_BIKE,
            ])],
            'items.*.item_id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.method' => ['required_with:payments', 'string', Rule::in([
                Payment::METHOD_CASH,
                Payment::METHOD_VISA,
                Payment::METHOD_INSTAPAY,
            ])],
            'payments.*.status' => ['nullable', 'string', Rule::in([
                Payment::STATUS_PENDING,
                Payment::STATUS_COMPLETED,
            ])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                if (($item['item_type'] ?? null) === SaleItem::ITEM_TYPE_BIKE && (int) ($item['qty'] ?? 0) !== 1) {
                    $validator->errors()->add("items.$index.qty", 'Bike quantity must be exactly 1.');
                }
            }
        });
    }
}
