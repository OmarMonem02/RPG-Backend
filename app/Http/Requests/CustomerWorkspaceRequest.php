<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_page' => ['nullable', 'integer', 'min:1'],
            'sales_per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'tickets_page' => ['nullable', 'integer', 'min:1'],
            'tickets_per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
