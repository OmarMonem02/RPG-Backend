<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StocktakeExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', Rule::in(['product', 'spare_part'])],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.counted' => ['required', 'integer', 'min:0'],
            'columns' => ['sometimes', 'array', 'min:1'],
            'columns.*' => ['required', 'string'],
        ];
    }
}
