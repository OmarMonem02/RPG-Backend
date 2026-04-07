<?php

namespace App\Http\Requests;

use App\Models\Seller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'commission_type' => ['sometimes', 'string', Rule::in([
                Seller::COMMISSION_TYPE_TOTAL,
                Seller::COMMISSION_TYPE_PROFIT,
            ])],
            'commission_value' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in([
                Seller::STATUS_ACTIVE,
                Seller::STATUS_INACTIVE,
            ])],
        ];
    }
}
