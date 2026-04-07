<?php

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class DeleteCustomerService
{
    public function execute(Customer $customer): void
    {
        if ($customer->sales()->exists() || $customer->tickets()->exists()) {
            throw ValidationException::withMessages([
                'customer' => 'Customer cannot be deleted while linked to sales or tickets.',
            ]);
        }

        $customer->delete();
    }
}
