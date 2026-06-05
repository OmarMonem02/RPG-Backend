<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalRequestApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_ADMIN;
    }

    public function rules(): array
    {
        return [
            'approved_discount_amount' => ['required', 'numeric', 'min:0.01'],
            'approved_discount_input_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'approved_discount_input_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
