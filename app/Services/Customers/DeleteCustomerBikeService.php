<?php

namespace App\Services\Customers;

use App\Models\CustomerBike;
use Illuminate\Validation\ValidationException;

class DeleteCustomerBikeService
{
    public function execute(CustomerBike $customerBike): void
    {
        if ($customerBike->tickets()->exists()) {
            throw ValidationException::withMessages([
                'customer_bike' => 'Customer bike cannot be deleted while linked to tickets.',
            ]);
        }

        $customerBike->delete();
    }
}
