<?php

namespace App\Services\Customers;

use App\Models\Customer;

class UpdateCustomerService
{
    public function execute(Customer $customer, array $data): Customer
    {
        $customer->update([
            'name' => $data['name'] ?? $customer->name,
            'phone' => $data['phone'] ?? $customer->phone,
            'address' => array_key_exists('address', $data) ? $data['address'] : $customer->address,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $customer->notes,
        ]);

        return $customer->fresh(['customerBikes']);
    }
}
