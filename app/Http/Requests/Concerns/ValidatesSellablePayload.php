<?php

namespace App\Http\Requests\Concerns;

use App\Models\SaleItem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesSellablePayload
{
  /**
   * @return array<string, array<int, mixed>>
   */
  protected function unstoredFieldRules(bool $requireSalePrice = true): array
  {
    $rules = [
      'is_unstored' => ['nullable', 'boolean'],
      'custom_name' => ['required_if:is_unstored,true', 'nullable', 'string', 'max:255'],
      'custom_description' => ['required_if:is_unstored,true', 'nullable', 'string', 'max:5000'],
      'unstored_type' => [
        'required_if:is_unstored,true',
        'nullable',
        'string',
        Rule::in(SaleItem::UNSTORED_TYPES),
      ],
      'cost_price' => ['required_if:is_unstored,true', 'nullable', 'numeric', 'min:0'],
    ];

    if ($requireSalePrice) {
      $rules['selling_price'] = ['required_if:is_unstored,true', 'nullable', 'numeric', 'min:0'];
    } else {
      $rules['price_snapshot'] = ['required_if:is_unstored,true', 'nullable', 'numeric', 'min:0'];
    }

    return $rules;
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  protected function validateLineItemReference(Validator $validator, array $payload, string $prefix = ''): void
  {
    $isUnstored = filter_var($payload['is_unstored'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($isUnstored) {
      $this->validateUnstoredLineItem($validator, $payload, $prefix);

      return;
    }

    $this->validateSingleSellableReference($validator, $payload, $prefix);
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  protected function validateUnstoredLineItem(Validator $validator, array $payload, string $prefix = ''): void
  {
    $fields = [
      'product_id',
      'spare_part_id',
      'maintenance_part_id',
      'maintenance_service_id',
      'bike_for_sale_id',
    ];

    foreach ($fields as $field) {
      if (! empty($payload[$field])) {
        $validator->errors()->add(
          $this->fieldKey($prefix, $field),
          'Catalog references are not allowed for unstored items.',
        );
      }
    }

    $required = ['custom_name', 'custom_description', 'unstored_type', 'cost_price'];
    foreach ($required as $field) {
      if (blank($payload[$field] ?? null)) {
        $validator->errors()->add(
          $this->fieldKey($prefix, $field),
          'This field is required for unstored items.',
        );
      }
    }

    $hasSalePrice = isset($payload['selling_price']) && $payload['selling_price'] !== '';
    $hasSnapshot = isset($payload['price_snapshot']) && $payload['price_snapshot'] !== '';
    if (! $hasSalePrice && ! $hasSnapshot) {
      $key = array_key_exists('selling_price', $payload) || ! array_key_exists('price_snapshot', $payload)
        ? 'selling_price'
        : 'price_snapshot';
      $validator->errors()->add(
        $this->fieldKey($prefix, $key),
        'Sale price is required for unstored items.',
      );
    }
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  protected function validateSingleSellableReference(Validator $validator, array $payload, string $prefix = ''): void
  {
    $fields = [
      'product_id',
      'spare_part_id',
      'maintenance_part_id',
      'maintenance_service_id',
      'bike_for_sale_id',
    ];

    $provided = collect($fields)
      ->filter(fn (string $field) => ! empty($payload[$field]))
      ->values();

    if ($provided->count() !== 1) {
      $validator->errors()->add(
        $this->fieldKey($prefix, 'item_type'),
        'Exactly one sellable item reference must be provided.',
      );

      return;
    }

    $qty = isset($payload['qty']) ? (int) $payload['qty'] : null;
    if (($payload['bike_for_sale_id'] ?? null) && $qty !== null && $qty !== 1) {
      $validator->errors()->add(
        $this->fieldKey($prefix, 'qty'),
        'Bike sale items must use a quantity of 1.',
      );
    }
  }

  protected function fieldKey(string $prefix, string $field): string
  {
    return $prefix === '' ? $field : $prefix . '.' . $field;
  }
}
