<?php

namespace App\Services\Sellers;

use App\Models\Seller;

class ShowSellerService
{
    public function execute(Seller $seller): array
    {
        $seller->loadMissing([
            'sales' => fn ($query) => $query
                ->with(['customer', 'seller', 'items', 'payments'])
                ->latest('id')
                ->limit(5),
        ]);

        $metrics = Seller::query()
            ->withSellerMetrics()
            ->findOrFail($seller->id);

        return [
            'seller' => $metrics,
            'last_sales' => $seller->sales,
        ];
    }
}
