<?php

namespace App\Traits;

use App\Models\InventoryImage;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasInventoryImages
{
    public function images(): MorphMany
    {
        return $this->morphMany(InventoryImage::class, 'imageable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryImage(): ?InventoryImage
    {
        if ($this->relationLoaded('images')) {
            return $this->images->firstWhere('is_primary', true)
                ?? $this->images->first();
        }

        return $this->images()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    public function getImageAttribute(): ?string
    {
        return $this->primaryImage()?->url;
    }

    public function getImagePublicIdAttribute(): ?string
    {
        return $this->primaryImage()?->public_id;
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    protected function appendInventoryImagesToArray(array $array): array
    {
        if ($this->relationLoaded('images')) {
            $primary = $this->images->firstWhere('is_primary', true)
                ?? $this->images->first();

            $array['images'] = $this->images
                ->map(fn (InventoryImage $image) => [
                    'url' => $image->url,
                    'public_id' => $image->public_id,
                    'is_primary' => $image->is_primary,
                    'sort_order' => $image->sort_order,
                ])
                ->values()
                ->all();

            $array['image'] = $primary?->url;
            $array['image_public_id'] = $primary?->public_id;
        }

        return $array;
    }
}
