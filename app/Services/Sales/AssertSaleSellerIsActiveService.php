<?php

namespace App\Services\Sales;

use App\Models\Seller;
use Illuminate\Validation\ValidationException;

class AssertSaleSellerIsActiveService
{
    public function execute(?Seller $seller): void
    {
        if ($seller === null || $seller->trashed()) {
            throw ValidationException::withMessages([
                'seller_id' => 'The selected seller is invalid.',
            ]);
        }

        if ($seller->status !== Seller::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'seller_id' => 'Inactive sellers cannot be used for sale operations.',
            ]);
        }
    }
}
