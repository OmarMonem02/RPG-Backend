<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerBike;

class CreateCustomerBikeService
{
    public function execute(Customer $customer, array $data): CustomerBike
    {
        return $customer->customerBikes()->create([
            'brand' => $data['brand'],
            'model' => $data['model'],
            'year' => $data['year'],
            'modifications' => $data['modifications'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
