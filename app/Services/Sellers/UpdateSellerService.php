<?php

namespace App\Services\Sellers;

use App\Models\Seller;

class UpdateSellerService
{
    public function execute(Seller $seller, array $data): Seller
    {
        $seller->fill($data)->save();

        return $seller->refresh();
    }
}
