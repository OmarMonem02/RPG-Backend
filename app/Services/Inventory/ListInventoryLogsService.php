<?php

namespace App\Services\Inventory;

use App\Models\StockLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListInventoryLogsService
{
    public function execute(array $filters = []): LengthAwarePaginator
    {
        return StockLog::query()
            ->with(['product', 'user'])
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['product_id']), fn ($query) => $query->where('product_id', $filters['product_id']))
            ->when(isset($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }
}
