<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_bike_id' => [
                'required',
                'integer',
                'exists:customer_bikes,id',
                Rule::exists('customer_bikes', 'id')->where(
                    fn ($query) => $query->where('customer_id', $this->input('customer_id'))
                ),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
