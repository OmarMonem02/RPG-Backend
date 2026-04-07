<?php

namespace App\Services\Sellers;

use App\Models\Seller;

class CreateSellerService
{
    public function execute(array $data): Seller
    {
        return Seller::query()->create([
            'name' => $data['name'],
            'commission_type' => $data['commission_type'],
            'commission_value' => $data['commission_value'],
            'status' => $data['status'] ?? Seller::STATUS_ACTIVE,
        ]);
    }
}
