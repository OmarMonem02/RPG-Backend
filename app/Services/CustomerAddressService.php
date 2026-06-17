<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Collection;

class CustomerAddressService
{
    /**
     * @return Collection<int, CustomerAddress>
     */
    public function listForCustomer(Customer $customer): Collection
    {
        return $customer->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Customer $customer, array $data): CustomerAddress
    {
        $hasAddresses = $customer->addresses()->exists();
        $isDefault = (bool) ($data['is_default'] ?? false) || ! $hasAddresses;

        if ($isDefault) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $address = $customer->addresses()->create([
            'label' => isset($data['label']) ? trim((string) $data['label']) ?: null : null,
            'full_address' => trim((string) $data['full_address']),
            'city' => trim((string) $data['city']),
            'is_default' => $isDefault,
        ]);

        if ($isDefault) {
            $this->syncCustomerDefaultAddress($customer);
        }

        return $address;
    }

    public function syncCustomerDefaultAddress(Customer $customer): void
    {
        $default = $customer->addresses()
            ->where('is_default', true)
            ->orderBy('id')
            ->first();

        $customer->forceFill([
            'address' => $default?->formatted(),
        ])->save();
    }

    public function serializeAddress(CustomerAddress $address): array
    {
        return [
            'id' => $address->id,
            'customer_id' => $address->customer_id,
            'label' => $address->label,
            'full_address' => $address->full_address,
            'city' => $address->city,
            'is_default' => (bool) $address->is_default,
            'formatted' => $address->formatted(),
            'created_at' => $address->created_at,
            'updated_at' => $address->updated_at,
        ];
    }
}
