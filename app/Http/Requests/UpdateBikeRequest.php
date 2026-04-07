<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bikeId = $this->route('bike')?->id ?? $this->route('bike');
        $brand = $this->input('brand', $this->route('bike')?->brand);
        $model = $this->input('model', $this->route('bike')?->model);

        return [
            'brand' => ['sometimes', 'required', 'string', 'max:255'],
            'model' => ['sometimes', 'required', 'string', 'max:255'],
            'year' => ['sometimes', 'required', 'integer', 'between:1900,'.(date('Y') + 1), Rule::unique('bikes')->ignore($bikeId)->where(
                fn ($query) => $query
                    ->where('brand', $brand)
                    ->where('model', $model)
            )],
        ];
    }
}
