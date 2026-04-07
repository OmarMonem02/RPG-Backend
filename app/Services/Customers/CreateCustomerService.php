<?php

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CreateCustomerService
{
    public function __construct(
        private readonly CreateCustomerBikeService $createCustomerBikeService,
    ) {}

    public function execute(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            $customer = Customer::query()->create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['bikes'] ?? [] as $bike) {
                $this->createCustomerBikeService->execute($customer, $bike);
            }

            return $customer->fresh(['customerBikes']);
        });
    }
}
