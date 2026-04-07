<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerBikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id ?? $this->integer('customer_id');

        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:1900,'.(date('Y') + 1), Rule::unique('customer_bikes')->where(
                fn ($query) => $query
                    ->where('customer_id', $customerId)
                    ->where('brand', $this->string('brand')->value())
                    ->where('model', $this->string('model')->value())
            )],
            'modifications' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
