<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', Rule::in([
                Payment::METHOD_CASH,
                Payment::METHOD_VISA,
                Payment::METHOD_INSTAPAY,
            ])],
            'status' => ['nullable', 'string', Rule::in([
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PENDING,
            ])],
        ];
    }
}
