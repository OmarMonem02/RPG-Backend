<?php

namespace App\Http\Requests;

use App\Models\Log;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', Rule::in([
                Log::ACTION_CREATE,
                Log::ACTION_UPDATE,
                Log::ACTION_DELETE,
            ])],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }
}
