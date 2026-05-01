<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TicketItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'spare_part_id' => ['nullable', 'exists:spare_parts,id'],
            'maintenance_service_id' => ['nullable', 'exists:maintenance_services,id'],
            'price_snapshot' => ['nullable', 'numeric'],
            'discount' => ['nullable', 'numeric'],
            'qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
