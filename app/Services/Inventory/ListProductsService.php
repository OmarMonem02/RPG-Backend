<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProductsService
{
    public function execute(
        ?string $search = null,
        ?string $type = null,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?bool $isUniversal = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return Product::query()
            ->with(['category', 'brand', 'units'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('part_number', 'like', '%'.$search.'%');
            }))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($isUniversal !== null, fn ($query) => $query->where('is_universal', $isUniversal))
            ->latest('id')
            ->paginate($perPage);
    }
}
