<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', Rule::unique('customers', 'phone')],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'bikes' => ['nullable', 'array'],
            'bikes.*.brand' => ['required_with:bikes', 'string', 'max:255'],
            'bikes.*.model' => ['required_with:bikes', 'string', 'max:255'],
            'bikes.*.year' => ['required_with:bikes', 'integer', 'between:1900,'.(date('Y') + 1)],
            'bikes.*.modifications' => ['nullable', 'string'],
            'bikes.*.notes' => ['nullable', 'string'],
        ];
    }
}
