<?php

namespace App\Services\Sellers;

use App\Models\Seller;

class DeleteSellerService
{
    public function execute(Seller $seller): void
    {
        $seller->delete();
    }
}
