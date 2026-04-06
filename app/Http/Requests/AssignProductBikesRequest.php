<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignProductBikesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bike_ids' => ['required', 'array'],
            'bike_ids.*' => ['integer', 'exists:bikes,id'],
        ];
    }
}
