<?php

namespace App\Services\Sellers;

use App\Models\Seller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListSellersService
{
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        return Seller::query()
            ->withSellerMetrics()
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where('name', 'like', "%{$search}%");
            })
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy($sortBy, $sortDirection)
            ->paginate($filters['per_page'] ?? 15);
    }
}
