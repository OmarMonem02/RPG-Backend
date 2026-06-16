<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatalogPricingRules
{
    /**
     * @return array<string, mixed>
     */
    public static function fieldRules(bool $isUpdate): array
    {
        $currencyIn = [Rule::in(config('currencies.supported'))];

        return [
            'cost_currency' => ['nullable', 'string', ...$currencyIn],
            'sale_currency' => ['nullable', 'string', ...$currencyIn],
            'sale_price_mode' => ['nullable', 'string', Rule::in(['manual', 'margin'])],
            'sale_margin_type' => ['nullable', 'string', Rule::in(['percentage', 'fixed'])],
            'sale_margin_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public static function validateMarginMode(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $mode = $validator->getData()['sale_price_mode'] ?? 'manual';
        if ($mode !== 'margin') {
            return;
        }

        $costCurrency = strtoupper((string) ($validator->getData()['cost_currency'] ?? 'EGP'));
        $saleCurrency = strtoupper((string) ($validator->getData()['sale_currency'] ?? 'EGP'));

        if (! in_array($costCurrency, ['USD', 'EUR'], true)) {
            $validator->errors()->add(
                'cost_currency',
                'Margin-based sale pricing requires cost currency to be USD or EUR.',
            );
        }

        if ($saleCurrency !== 'EGP') {
            $validator->errors()->add(
                'sale_currency',
                'Margin-based sale pricing requires sale currency to be EGP.',
            );
        }

        $marginType = $validator->getData()['sale_margin_type'] ?? null;
        $marginValue = $validator->getData()['sale_margin_value'] ?? null;

        if (! $marginType) {
            $validator->errors()->add('sale_margin_type', 'Margin type is required when using margin-based sale pricing.');
        }

        if ($marginValue === null || $marginValue === '') {
            $validator->errors()->add('sale_margin_value', 'Margin value is required when using margin-based sale pricing.');
        }
    }
}
