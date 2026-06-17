<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class CatalogItemAttributeRules
{
    /**
     * @return array<string, mixed>
     */
    public static function fieldRules(bool $isUpdate = false): array
    {
        return [
            'size' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'item_status' => [$isUpdate ? 'nullable' : 'required', Rule::enum(ItemStatus::class)],
        ];
    }
}
