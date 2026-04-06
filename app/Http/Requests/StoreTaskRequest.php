<?php

namespace App\Http\Requests;

use App\Models\TicketTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in([
                TicketTask::STATUS_PENDING,
                TicketTask::STATUS_COMPLETED,
            ])],
            'approved_by_client' => ['nullable', 'boolean'],
        ];
    }
}
