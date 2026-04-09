<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in([
                Sale::STATUS_PENDING,
                Sale::STATUS_PARTIAL,
                Sale::STATUS_COMPLETED,
            ])],
            'type' => ['nullable', 'string', Rule::in([
                Sale::TYPE_GARAGE,
                Sale::TYPE_DELIVERY,
                Sale::TYPE_ONLINE,
            ])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'sale_source' => ['nullable', 'string', Rule::in(['seller_based', 'direct'])],
            'payment_status' => ['nullable', 'string', Rule::in([
                'unpaid',
                'partial',
                'paid',
            ])],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'sort_by' => ['nullable', 'string', Rule::in([
                'id',
                'created_at',
                'updated_at',
                'total',
                'final_amount',
                'paid_amount',
                'remaining_amount',
            ])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
