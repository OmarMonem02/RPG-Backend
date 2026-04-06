<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', Rule::in([
                Expense::CATEGORY_GOODS,
                Expense::CATEGORY_BILLS,
                Expense::CATEGORY_SUPPLIES,
            ])],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'min_amount' => ['nullable', 'numeric', 'gte:0'],
            'max_amount' => ['nullable', 'numeric', 'gte:min_amount'],
            'search' => ['nullable', 'string', 'max:255'],
            'paid_by' => ['nullable', 'string', Rule::in([
                Expense::PAID_BY_CASH,
                Expense::PAID_BY_BANK,
            ])],
        ];
    }
}
