<?php

namespace App\Services\Sellers;

use App\Models\Seller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSellerSalesService
{
    public function execute(Seller $seller, array $filters = []): LengthAwarePaginator
    {
        return $seller->sales()
            ->with(['customer', 'seller', 'items', 'payments'])
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }
}
