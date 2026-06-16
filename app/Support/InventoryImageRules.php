<?php

namespace App\Support;

use App\Services\InventoryImageService;
use Illuminate\Validation\Validator;

class InventoryImageRules
{
    /**
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'images' => ['nullable', 'array', 'max:'.InventoryImageService::MAX_IMAGES],
            'images.*.url' => ['required', 'url'],
            'images.*.public_id' => ['nullable', 'string', 'max:255'],
            'images.*.is_primary' => ['required', 'boolean'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:3'],
        ];
    }

    public static function validatePrimarySelection(Validator $validator): void
    {
        $images = $validator->getData()['images'] ?? null;
        if (! is_array($images) || $images === []) {
            return;
        }

        $primaryCount = collect($images)
            ->filter(fn ($image) => is_array($image) && filter_var($image['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->count();

        if ($primaryCount !== 1) {
            $validator->errors()->add(
                'images',
                'Exactly one image must be marked as primary when images are provided.'
            );
        }
    }
}
