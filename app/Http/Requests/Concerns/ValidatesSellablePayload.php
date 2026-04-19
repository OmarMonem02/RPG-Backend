<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesSellablePayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validateSingleSellableReference(Validator $validator, array $payload, string $prefix = ''): void
    {
        $fields = [
            'product_id',
            'spare_part_id',
            'maintenance_service_id',
            'bike_for_sale_id',
        ];

        $provided = collect($fields)
            ->filter(fn (string $field) => ! empty($payload[$field]))
            ->values();

        if ($provided->count() !== 1) {
            $validator->errors()->add(
                $prefix === '' ? 'item_type' : $prefix . '.item_type',
                'Exactly one sellable item reference must be provided.'
            );

            return;
        }

        $qty = isset($payload['qty']) ? (int) $payload['qty'] : null;
        if (($payload['bike_for_sale_id'] ?? null) && $qty !== null && $qty !== 1) {
            $validator->errors()->add(
                $prefix === '' ? 'qty' : $prefix . '.qty',
                'Bike sale items must use a quantity of 1.'
            );
        }
    }
}
