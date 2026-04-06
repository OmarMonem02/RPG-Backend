<?php

namespace App\Http\Requests;

use App\Models\SaleItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddSaleItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_type' => ['required', 'string', Rule::in([
                SaleItem::ITEM_TYPE_PRODUCT,
                SaleItem::ITEM_TYPE_BIKE,
            ])],
            'item_id' => ['required', 'integer', 'min:1'],
            'qty' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('item_type') === SaleItem::ITEM_TYPE_BIKE && (int) $this->input('qty') !== 1) {
                $validator->errors()->add('qty', 'Bike quantity must be exactly 1.');
            }
        });
    }
}
