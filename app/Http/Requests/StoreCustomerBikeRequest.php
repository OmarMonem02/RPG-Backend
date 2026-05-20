<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerBikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bike_blueprint_id' => ['required', 'exists:bike_blueprints,id'],
            'image' => ['nullable', 'url'],
            'image_public_id' => ['nullable', 'string', 'max:255'],
            'vin' => ['nullable', 'string', 'max:255'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
