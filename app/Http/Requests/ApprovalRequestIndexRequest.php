<?php

namespace App\Http\Requests;

use App\Models\ApprovalRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'type' => ['nullable', 'string', Rule::in([ApprovalRequest::TYPE_SALE_DISCOUNT])],
            'status' => ['nullable', 'string', Rule::in([
                ApprovalRequest::STATUS_PENDING,
                ApprovalRequest::STATUS_APPROVED,
                ApprovalRequest::STATUS_REJECTED,
                ApprovalRequest::STATUS_CANCELLED,
                ApprovalRequest::STATUS_CONSUMED,
            ])],
        ];
    }
}
