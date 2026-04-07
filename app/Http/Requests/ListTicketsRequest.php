<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in([
                Ticket::STATUS_PENDING,
                Ticket::STATUS_IN_PROGRESS,
                Ticket::STATUS_COMPLETED,
            ])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_bike_id' => ['nullable', 'integer', 'exists:customer_bikes,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
