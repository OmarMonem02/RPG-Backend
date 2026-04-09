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
        ?bool $inStock = null,
        ?bool $lowStock = null,
        ?bool $hasUnits = null,
        string $sortBy = 'id',
        string $sortDirection = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        $allowedSorts = [
            'id',
            'name',
            'qty',
            'selling_price',
            'created_at',
            'updated_at',
        ];

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        return Product::query()
            ->with(['category', 'brand', 'units'])
            ->withCount('bikes')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('part_number', 'like', '%'.$search.'%');
            }))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($isUniversal !== null, fn ($query) => $query->where('is_universal', $isUniversal))
            ->when($inStock !== null, fn ($query) => $query->where('qty', $inStock ? '>' : '<=', 0))
            ->when(
                $lowStock !== null,
                fn ($query) => $query->where(function ($query) use ($lowStock): void {
                    if ($lowStock) {
                        $query->where('qty', '>', 0)
                            ->where('qty', '<=', Product::LOW_STOCK_THRESHOLD);

                        return;
                    }

                    $query->where('qty', '<=', 0)
                        ->orWhere('qty', '>', Product::LOW_STOCK_THRESHOLD);
                })
            )
            ->when($hasUnits !== null, function ($query) use ($hasUnits): void {
                if ($hasUnits) {
                    $query->has('units');

                    return;
                }

                $query->doesntHave('units');
            })
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }
}
