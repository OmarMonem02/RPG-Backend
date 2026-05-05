<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'folder' => [
                'sometimes',
                'string',
                'in:rpg-system/bikes,rpg-system/products,rpg-system/spare-parts,rpg-system/expenses,rpg-system/Customer-Bike,rpg-system/general',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => 'Image must be 5MB or less.',
            'image.mimes' => 'Only JPG, PNG, and WebP images are accepted.',
        ];
    }
}
