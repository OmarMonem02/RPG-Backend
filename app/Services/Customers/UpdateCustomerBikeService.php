<?php

namespace App\Services\Customers;

use App\Models\CustomerBike;

class UpdateCustomerBikeService
{
    public function execute(CustomerBike $customerBike, array $data): CustomerBike
    {
        $customerBike->update([
            'customer_id' => $data['customer_id'] ?? $customerBike->customer_id,
            'brand' => $data['brand'] ?? $customerBike->brand,
            'model' => $data['model'] ?? $customerBike->model,
            'year' => $data['year'] ?? $customerBike->year,
            'modifications' => array_key_exists('modifications', $data) ? $data['modifications'] : $customerBike->modifications,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $customerBike->notes,
        ]);

        return $customerBike->fresh(['customer']);
    }
}
