<?php

namespace App\Services\Inventory;

use App\Models\Bike;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCompatibleProductsService
{
    public function execute(
        ?int $bikeId = null,
        ?string $brand = null,
        ?string $model = null,
        ?int $year = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        if ($bikeId !== null) {
            $bike = Bike::query()->findOrFail($bikeId);
            $brand = $bike->brand;
            $model = $bike->model;
            $year = $bike->year;
        }

        return Product::query()
            ->with(['units', 'bikes', 'category', 'brand'])
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
            ->latest('id')
            ->paginate($perPage);
    }
}
