<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ListCompatibleProductsService
{
    public function execute(?string $brand = null, ?string $model = null, ?int $year = null): Collection
    {
        return Product::query()
            ->with(['units', 'bikes'])
            ->when(
                $brand !== null && $model !== null && $year !== null,
                fn ($query) => $query->where(function ($query) use ($brand, $model, $year): void {
                    $query->where('is_universal', true)
                        ->orWhereHas('bikes', function ($query) use ($brand, $model, $year): void {
                            $query->where('brand', $brand)
                                ->where('model', $model)
                                ->where('year', $year);
                        });
                }),
                fn ($query) => $query
            )
            ->get();
    }
}
