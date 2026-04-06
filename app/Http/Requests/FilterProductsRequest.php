<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand' => ['required_with:model,year', 'string', 'max:255'],
            'model' => ['required_with:brand,year', 'string', 'max:255'],
            'year' => ['required_with:brand,model', 'integer'],
        ];
    }
}
