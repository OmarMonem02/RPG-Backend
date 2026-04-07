<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSalePaymentsService
{
    public function execute(Sale $sale, int $perPage = 15): LengthAwarePaginator
    {
        return Payment::query()
            ->where('sale_id', $sale->id)
            ->latest('id')
            ->paginate($perPage);
    }
}
