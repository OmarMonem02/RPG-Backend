<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'image' => ['nullable', 'url'],
            'image_public_id' => ['nullable', 'string', 'max:255'],
            'category' => [$isUpdate ? 'sometimes' : 'required', Rule::in(Expense::CATEGORIES)],
            'amount' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'currency' => [$isUpdate ? 'sometimes' : 'required', Rule::in(['EGP', 'USD'])],
            'payment_status' => [$isUpdate ? 'sometimes' : 'required', Rule::in([Expense::STATUS_PAID, Expense::STATUS_UNPAID])],
            'incurred_on' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
