<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkInventoryEditRequest extends FormRequest
{
    public const PRICE_MODES = ['set', 'add', 'subtract', 'percent'];

    public const STOCK_MODES = ['set', 'add', 'subtract'];

    public const ALLOWED_FIELDS = [
        'sale_price',
        'cost_price',
        'stock_quantity',
        'low_stock_alarm',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currencyIn = [Rule::in(config('currencies.supported', ['EGP', 'USD', 'EUR']))];

        $priceChangeRules = [
            'nullable',
            'array',
            'required_array_keys:mode,value',
        ];

        $stockChangeRules = [
            'nullable',
            'array',
            'required_array_keys:mode,value',
        ];

        return [
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'min:1'],
            'filters' => ['nullable', 'array'],
            'filters.search' => ['nullable', 'string', 'max:255'],
            'filters.brand_id' => ['nullable', 'integer', 'min:1'],
            'filters.category_id' => ['nullable', 'integer', 'min:1'],
            'filters.currency' => ['nullable', 'string', ...$currencyIn],
            'changes' => ['required', 'array', 'min:1'],
            'changes.sale_price' => $priceChangeRules,
            'changes.sale_price.mode' => ['required_with:changes.sale_price', 'string', Rule::in(self::PRICE_MODES)],
            'changes.sale_price.value' => ['required_with:changes.sale_price', 'numeric'],
            'changes.cost_price' => $priceChangeRules,
            'changes.cost_price.mode' => ['required_with:changes.cost_price', 'string', Rule::in(self::PRICE_MODES)],
            'changes.cost_price.value' => ['required_with:changes.cost_price', 'numeric'],
            'changes.stock_quantity' => $stockChangeRules,
            'changes.stock_quantity.mode' => ['required_with:changes.stock_quantity', 'string', Rule::in(self::STOCK_MODES)],
            'changes.stock_quantity.value' => ['required_with:changes.stock_quantity', 'numeric'],
            'changes.low_stock_alarm' => $stockChangeRules,
            'changes.low_stock_alarm.mode' => ['required_with:changes.low_stock_alarm', 'string', Rule::in(self::STOCK_MODES)],
            'changes.low_stock_alarm.value' => ['required_with:changes.low_stock_alarm', 'numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('ids', []);
            $filters = $this->input('filters', []);
            $changes = $this->input('changes', []);

            if (empty($ids) && empty(array_filter($filters ?? [], fn ($v) => $v !== null && $v !== ''))) {
                $validator->errors()->add('ids', 'Provide at least one item id or a filter to select items.');
            }

            $hasChange = false;
            foreach (self::ALLOWED_FIELDS as $field) {
                if (! empty($changes[$field])) {
                    $hasChange = true;
                    break;
                }
            }
            if (! $hasChange) {
                $validator->errors()->add('changes', 'At least one field change is required.');
            }

            foreach (['sale_price', 'cost_price'] as $field) {
                $block = $changes[$field] ?? null;
                if (! $block || ($block['mode'] ?? '') !== 'percent') {
                    continue;
                }
                $value = (float) ($block['value'] ?? 0);
                if ($value <= -100) {
                    $validator->errors()->add("changes.{$field}.value", 'Percentage must be greater than -100.');
                }
            }
        });
    }

    /**
     * @return array<string, array{mode: string, value: float|int}>
     */
    public function normalizedChanges(): array
    {
        $changes = $this->validated('changes', []);
        $normalized = [];

        foreach (self::ALLOWED_FIELDS as $field) {
            if (empty($changes[$field])) {
                continue;
            }
            $normalized[$field] = [
                'mode' => (string) $changes[$field]['mode'],
                'value' => $changes[$field]['value'],
            ];
        }

        return $normalized;
    }
}
