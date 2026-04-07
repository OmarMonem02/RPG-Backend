<?php

namespace App\Services\Search;

use App\Models\Bike;
use App\Models\Customer;
use App\Models\Product;

class GlobalSearchService
{
    public function execute(string $query, int $limit = 10): array
    {
        return [
            'products' => Product::query()
                ->select(['id', 'name', 'sku', 'part_number', 'selling_price', 'qty'])
                ->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhere('part_number', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get(),
            'customers' => Customer::query()
                ->select(['id', 'name', 'phone', 'address'])
                ->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get(),
            'bikes' => Bike::query()
                ->select(['id', 'brand', 'model', 'year'])
                ->where(function ($builder) use ($query): void {
                    $builder->where('brand', 'like', "%{$query}%")
                        ->orWhere('model', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get(),
        ];
    }
}
