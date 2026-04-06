<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'date' => ['nullable', 'date'],
            'format' => ['nullable', 'string', 'in:json,csv,excel,pdf'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasDate = $this->filled('date');
            $hasRange = $this->filled('from_date') && $this->filled('to_date');

            if (! $hasDate && ! $hasRange) {
                $validator->errors()->add('from_date', 'Provide either a date or both from_date and to_date.');
            }
        });
    }
}
