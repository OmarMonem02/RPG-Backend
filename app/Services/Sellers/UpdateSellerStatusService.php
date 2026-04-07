<?php

namespace App\Services\Sellers;

use App\Models\Seller;

class UpdateSellerStatusService
{
    public function execute(Seller $seller, string $status): Seller
    {
        $seller->update([
            'status' => $status,
        ]);

        return $seller->refresh();
    }
}
