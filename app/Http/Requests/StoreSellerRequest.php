<?php

namespace App\Http\Requests;

use App\Models\Seller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'commission_type' => ['required', 'string', Rule::in([
                Seller::COMMISSION_TYPE_TOTAL,
                Seller::COMMISSION_TYPE_PROFIT,
            ])],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in([
                Seller::STATUS_ACTIVE,
                Seller::STATUS_INACTIVE,
            ])],
        ];
    }
}
