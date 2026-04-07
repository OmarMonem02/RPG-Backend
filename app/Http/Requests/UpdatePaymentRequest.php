<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'method' => ['sometimes', 'string', Rule::in([
                Payment::METHOD_CASH,
                Payment::METHOD_VISA,
                Payment::METHOD_INSTAPAY,
            ])],
            'status' => ['sometimes', 'string', Rule::in([
                Payment::STATUS_PENDING,
                Payment::STATUS_COMPLETED,
                Payment::STATUS_REFUNDED,
            ])],
        ];
    }
}
