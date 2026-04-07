<?php

namespace App\Http\Requests;

use App\Models\StockLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('product') !== null && ! $this->filled('product_id')) {
            $product = $this->route('product');

            $this->merge([
                'product_id' => is_object($product) ? $product->id : $product,
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'change_type' => ['required', 'string', Rule::in([
                StockLog::CHANGE_TYPE_ADD,
                StockLog::CHANGE_TYPE_REDUCE,
                StockLog::CHANGE_TYPE_RETURN,
            ])],
            'unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'reference_type' => ['required', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
        ];
    }
}
