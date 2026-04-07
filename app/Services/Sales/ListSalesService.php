<?php

namespace App\Services\Sales;

use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSalesService
{
    public function execute(array $filters = []): LengthAwarePaginator
    {
        return Sale::query()
            ->with(['customer', 'seller', 'items', 'payments'])
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['customer_id']), fn ($query) => $query->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['seller_id']), fn ($query) => $query->where('seller_id', $filters['seller_id']))
            ->when(
                ($filters['sale_source'] ?? null) === 'seller_based',
                fn ($query) => $query->whereNotNull('seller_id')
            )
            ->when(
                ($filters['sale_source'] ?? null) === 'direct',
                fn ($query) => $query->whereNull('seller_id')
            )
            ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }
}
