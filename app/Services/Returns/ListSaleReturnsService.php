<?php

namespace App\Services\Returns;

use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Database\Eloquent\Collection;

class ListSaleReturnsService
{
    public function execute(Sale $sale): Collection
    {
        return SaleReturn::query()
            ->where('sale_id', $sale->id)
            ->with(['item', 'creator'])
            ->latest('id')
            ->get();
    }
}
