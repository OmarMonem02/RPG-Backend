<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_name' => ['required', 'string', 'max:255'],
            'conversion_factor' => ['required', 'numeric', 'gt:0'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
