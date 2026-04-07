<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerBikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerBike = $this->route('customerBike');
        $customerBikeId = $customerBike?->id ?? $customerBike;
        $customerId = $this->input('customer_id', $customerBike?->customer_id);
        $brand = $this->input('brand', $customerBike?->brand);
        $model = $this->input('model', $customerBike?->model);

        return [
            'customer_id' => ['sometimes', 'required', 'integer', 'exists:customers,id'],
            'brand' => ['sometimes', 'required', 'string', 'max:255'],
            'model' => ['sometimes', 'required', 'string', 'max:255'],
            'year' => ['sometimes', 'required', 'integer', 'between:1900,'.(date('Y') + 1), Rule::unique('customer_bikes')->ignore($customerBikeId)->where(
                fn ($query) => $query
                    ->where('customer_id', $customerId)
                    ->where('brand', $brand)
                    ->where('model', $model)
            )],
            'modifications' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
