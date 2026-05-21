<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\HistoryCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_ADMIN;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'entity_type' => ['nullable', 'string', Rule::in(HistoryCatalog::entityTypes())],
            'model_type' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', Rule::in(['create', 'update', 'delete'])],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'model_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
