<?php

namespace App\Http\Requests;

use App\Support\ItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkInventoryEditRequest extends FormRequest
{
    public const PRICE_MODES = ['set', 'add', 'subtract', 'percent'];

    public const STOCK_MODES = ['set', 'add', 'subtract'];

    public const SET_MODES = ['set'];

    public const ALLOWED_FIELDS = [
        'sale_price',
        'cost_price',
        'stock_quantity',
        'low_stock_alarm',
        'item_status',
        'have_commission',
        'max_discount_type',
        'max_discount_value',
    ];

    public const NUMERIC_FIELDS = [
        'sale_price',
        'cost_price',
        'stock_quantity',
        'low_stock_alarm',
        'max_discount_value',
    ];

    public const SET_ONLY_FIELDS = [
        'item_status',
        'have_commission',
        'max_discount_type',
        'max_discount_value',
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

        $setChangeRules = [
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
            'filters.price_range' => ['nullable', 'string', 'max:64'],
            'filters.cost_price_range' => ['nullable', 'string', 'max:64'],
            'filters.low_stock' => ['nullable', 'boolean'],
            'filters.bike_brand_id' => ['nullable', 'integer', 'min:1'],
            'filters.bike_model' => ['nullable', 'string', 'max:255'],
            'filters.bike_year' => ['nullable', 'integer'],
            'filters.bike_year_from' => ['nullable', 'integer'],
            'filters.bike_year_to' => ['nullable', 'integer'],
            'filters.tags' => ['nullable', 'string', 'max:500'],
            'filters.stock_min' => ['nullable', 'integer', 'min:0'],
            'filters.stock_max' => ['nullable', 'integer', 'min:0'],
            'filters.item_status' => ['nullable', 'string', Rule::in(ItemStatus::values())],
            'filters.size' => ['nullable', 'string', 'max:100'],
            'filters.color' => ['nullable', 'string', 'max:100'],
            'filters.universal' => ['nullable'],
            'filters.max_discount_min' => ['nullable', 'numeric', 'min:0'],
            'filters.max_discount_max' => ['nullable', 'numeric', 'min:0'],
            'filters.profit_min' => ['nullable', 'numeric'],
            'filters.profit_max' => ['nullable', 'numeric'],
            'filters.profit_percent_min' => ['nullable', 'numeric'],
            'filters.profit_percent_max' => ['nullable', 'numeric'],
            'filters.stock_alert_level' => ['nullable', 'string', 'max:32'],
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
            'changes.item_status' => $setChangeRules,
            'changes.item_status.mode' => ['required_with:changes.item_status', 'string', Rule::in(self::SET_MODES)],
            'changes.item_status.value' => ['required_with:changes.item_status', 'string', Rule::in(ItemStatus::values())],
            'changes.have_commission' => $setChangeRules,
            'changes.have_commission.mode' => ['required_with:changes.have_commission', 'string', Rule::in(self::SET_MODES)],
            'changes.have_commission.value' => ['required_with:changes.have_commission', 'boolean'],
            'changes.max_discount_type' => $setChangeRules,
            'changes.max_discount_type.mode' => ['required_with:changes.max_discount_type', 'string', Rule::in(self::SET_MODES)],
            'changes.max_discount_type.value' => ['required_with:changes.max_discount_type', 'string', Rule::in(['fixed', 'percentage'])],
            'changes.max_discount_value' => $setChangeRules,
            'changes.max_discount_value.mode' => ['required_with:changes.max_discount_value', 'string', Rule::in(self::SET_MODES)],
            'changes.max_discount_value.value' => ['required_with:changes.max_discount_value', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('ids', []);
            $filters = $this->input('filters', []);
            $changes = $this->input('changes', []);

            if (empty($ids) && ! self::hasActiveFilters($filters)) {
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
     * @return array<string, array{mode: string, value: mixed}>
     */
    public function normalizedChanges(): array
    {
        $changes = $this->validated('changes', []);
        $normalized = [];

        foreach (self::ALLOWED_FIELDS as $field) {
            if (empty($changes[$field])) {
                continue;
            }
            $value = $changes[$field]['value'];
            if ($field === 'have_commission') {
                $value = (bool) $value;
            }
            if ($field === 'max_discount_value') {
                $value = (float) $value;
            }

            $normalized[$field] = [
                'mode' => (string) $changes[$field]['mode'],
                'value' => $value,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function hasActiveFilters(?array $filters): bool
    {
        if (empty($filters)) {
            return false;
        }

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || $value === 'all') {
                continue;
            }
            if ($key === 'low_stock' && $value === false) {
                continue;
            }
            if (is_array($value) && count($value) === 0) {
                continue;
            }

            return true;
        }

        return false;
    }
}
