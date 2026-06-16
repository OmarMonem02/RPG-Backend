<?php

namespace App\Support\ImportExport;

use App\Models\InventoryImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ImportExportImageHelper
{
    public const IMAGE_COLUMN_KEYS = ['image_1', 'image_2', 'image_3', 'image_4'];

    /**
     * @return array<int, array{url: string, public_id: ?string, is_primary: bool, sort_order: int}>
     */
    public function imagesFromImportRow(array $row): array
    {
        $row = $this->applyLegacyImageFallback($row);

        $images = [];
        foreach (self::IMAGE_COLUMN_KEYS as $index => $key) {
            $url = trim((string) ($row[$key] ?? ''));
            if ($url === '') {
                continue;
            }

            $images[] = [
                'url' => $url,
                'public_id' => $this->extractCloudinaryPublicId($url),
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ];
        }

        if ($images === []) {
            return [];
        }

        $hasPrimaryInFirstSlot = trim((string) ($row['image_1'] ?? '')) !== '';
        if (! $hasPrimaryInFirstSlot) {
            $images[0]['is_primary'] = true;
            foreach ($images as $i => &$image) {
                $image['is_primary'] = $i === 0;
            }
            unset($image);
        }

        return $images;
    }

    /**
     * @param  Collection<int, InventoryImage>|Collection<int, object{url: string}>  $images
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    public function exportImageColumns(Collection $images): array
    {
        $sorted = $images
            ->sortBy(fn ($image) => [
                $image->is_primary ?? false ? 0 : 1,
                $image->sort_order ?? 0,
                $image->id ?? 0,
            ])
            ->values();

        $columns = [null, null, null, null];
        foreach ($sorted->take(4) as $index => $image) {
            $columns[$index] = $image->url ?? null;
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeImportImageRow(array $row): array
    {
        return $this->applyLegacyImageFallback($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function applyLegacyImageFallback(array $row): array
    {
        if (trim((string) ($row['image_1'] ?? '')) !== '') {
            return $row;
        }

        foreach (['image', 'image_url'] as $legacyKey) {
            $legacyUrl = trim((string) ($row[$legacyKey] ?? ''));
            if ($legacyUrl !== '') {
                $row['image_1'] = $legacyUrl;
                break;
            }
        }

        return $row;
    }

    private function extractCloudinaryPublicId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_contains($path, '/image/upload/')) {
            return null;
        }

        $segments = explode('/', Str::after($path, '/image/upload/'));

        if ($segments === []) {
            return null;
        }

        if (isset($segments[0]) && preg_match('/^v\d+$/', $segments[0])) {
            array_shift($segments);
        }

        while (isset($segments[0]) && (str_contains($segments[0], ',') || (str_contains($segments[0], '_') && ! str_contains($segments[0], '.')))) {
            array_shift($segments);
        }

        $publicId = preg_replace('/\.[^.]+$/', '', implode('/', $segments));

        return $publicId !== '' ? $publicId : null;
    }
}
