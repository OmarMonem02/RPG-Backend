<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in([
                Expense::CATEGORY_GOODS,
                Expense::CATEGORY_BILLS,
                Expense::CATEGORY_SUPPLIES,
            ])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'expense_date' => ['required', 'date'],
            'paid_by' => ['required', 'string', Rule::in([
                Expense::PAID_BY_CASH,
                Expense::PAID_BY_BANK,
            ])],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_type' => ['nullable', 'string', Rule::in([
                Expense::RECURRING_WEEKLY,
                Expense::RECURRING_MONTHLY,
                Expense::RECURRING_YEARLY,
                Expense::RECURRING_NONE,
            ])],
        ];
    }
}
