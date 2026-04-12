<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BikeBlueprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => 'required|integer|exists:brands,id',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . date('Y') + 10,
        ];
    }

    public function messages(): array
    {
        return [
            'brand_id.exists' => 'Selected brand does not exist.',
            'year.max' => 'Year cannot be more than 10 years in the future.',
        ];
    }
}
