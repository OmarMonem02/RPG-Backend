<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class InventoryImageService
{
    public const MAX_IMAGES = 4;

    /**
     * @param  array<int, array<string, mixed>>|null  $images
     */
    public function syncImages(Model $model, ?array $images): void
    {
        if ($images === null) {
            return;
        }

        $normalized = $this->normalizeImages($images);

        $model->images()->delete();

        foreach ($normalized as $image) {
            $model->images()->create($image);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $images
     * @return array<int, array{url: string, public_id: ?string, is_primary: bool, sort_order: int}>
     */
    public function normalizeImages(array $images): array
    {
        if (count($images) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'A maximum of '.self::MAX_IMAGES.' images is allowed per item.',
            ]);
        }

        $normalized = [];
        foreach (array_values($images) as $index => $image) {
            $url = trim((string) ($image['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $normalized[] = [
                'url' => $url,
                'public_id' => isset($image['public_id']) && $image['public_id'] !== ''
                    ? (string) $image['public_id']
                    : null,
                'is_primary' => (bool) ($image['is_primary'] ?? false),
                'sort_order' => isset($image['sort_order'])
                    ? (int) $image['sort_order']
                    : $index,
            ];
        }

        if ($normalized === []) {
            return [];
        }

        $primaryIndexes = [];
        foreach ($normalized as $index => $image) {
            if ($image['is_primary']) {
                $primaryIndexes[] = $index;
            }
        }

        if (count($primaryIndexes) !== 1) {
            foreach ($normalized as $index => &$image) {
                $image['is_primary'] = $index === 0;
            }
            unset($image);
        }

        usort($normalized, fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order']);

        return $normalized;
    }
}
