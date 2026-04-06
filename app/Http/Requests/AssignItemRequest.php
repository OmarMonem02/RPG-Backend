<?php

namespace App\Http\Requests;

use App\Models\TicketItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_type' => ['required', 'string', Rule::in([
                TicketItem::ITEM_TYPE_PRODUCT,
                TicketItem::ITEM_TYPE_SERVICE,
            ])],
            'item_id' => ['required', 'integer', 'min:1'],
            'qty' => ['required', 'integer', 'min:1'],
            'price_source' => ['nullable', 'string', Rule::in([
                TicketItem::PRICE_SOURCE_CURRENT,
                TicketItem::PRICE_SOURCE_OLD,
            ])],
        ];
    }
}
