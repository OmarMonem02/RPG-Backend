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
        'universal',
        'bike_blueprint_ids',
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
        'universal',
        'bike_blueprint_ids',
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
            'changes.universal' => $setChangeRules,
            'changes.universal.mode' => ['required_with:changes.universal', 'string', Rule::in(self::SET_MODES)],
            'changes.universal.value' => ['required_with:changes.universal', 'boolean'],
            'changes.bike_blueprint_ids' => $setChangeRules,
            'changes.bike_blueprint_ids.mode' => ['required_with:changes.bike_blueprint_ids', 'string', Rule::in(self::SET_MODES)],
            'changes.bike_blueprint_ids.value' => ['required_with:changes.bike_blueprint_ids', 'array', 'min:1'],
            'changes.bike_blueprint_ids.value.*' => ['integer', 'min:1'],
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

            $universalBlock = $changes['universal'] ?? null;
            $blueprintBlock = $changes['bike_blueprint_ids'] ?? null;
            if ($universalBlock !== null && ($universalBlock['value'] ?? null) === false) {
                $blueprintIds = $blueprintBlock['value'] ?? [];
                if (! is_array($blueprintIds) || count($blueprintIds) === 0) {
                    $validator->errors()->add(
                        'changes.bike_blueprint_ids',
                        'At least one bike blueprint is required when compatibility is set to Specific.',
                    );
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
            if ($field === 'bike_blueprint_ids' && is_array($value)) {
                $value = array_values(array_map('intval', $value));
            }
            if ($field === 'have_commission' || $field === 'universal') {
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

    public function touchesCompatibility(): bool
    {
        $changes = $this->normalizedChanges();

        return isset($changes['universal']) || isset($changes['bike_blueprint_ids']);
    }
}
