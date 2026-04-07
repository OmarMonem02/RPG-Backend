<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:1900,'.(date('Y') + 1), Rule::unique('bikes')->where(
                fn ($query) => $query
                    ->where('brand', $this->string('brand')->value())
                    ->where('model', $this->string('model')->value())
            )],
        ];
    }
}
