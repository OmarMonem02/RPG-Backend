<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_bike_id' => ['required', 'exists:customer_bikes,id'],
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
            'notes' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.name' => ['required', 'string'],
            'tasks.*.status' => ['required', Rule::in(['pending', 'completed'])],
            'tasks.*.items' => ['required', 'array', 'min:1'],
            'tasks.*.items.*.spare_part_id' => ['nullable', 'exists:spare_parts,id'],
            'tasks.*.items.*.maintenance_service_id' => ['nullable', 'exists:maintenance_services,id'],
            'tasks.*.items.*.price_snapshot' => ['required', 'numeric'],
            'tasks.*.items.*.discount' => ['nullable', 'numeric'],
            'tasks.*.items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
