<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('has_unstored_items')) {
            return;
        }

        $raw = $this->input('has_unstored_items');

        if (in_array($raw, [true, 1, '1', 'true', 'on', 'yes'], true)) {
            $this->merge(['has_unstored_items' => true]);

            return;
        }

        if (in_array($raw, [false, 0, '0', 'false', 'off', 'no'], true)) {
            $this->merge(['has_unstored_items' => false]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed', 'closed', 'cancelled', 'partial'])],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'has_unstored_items' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
