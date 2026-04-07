<?php

namespace App\Http\Requests;

use App\Models\Sale;
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
        ];
    }
}
