<?php

namespace Tests\Support;

use App\Models\Seller;

class SellerTestFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function create(array $overrides = []): Seller
    {
        $rate = (float) ($overrides['commission_rate'] ?? $overrides['products_commission_rate'] ?? 0);
        unset($overrides['commission_rate']);

        return Seller::query()->create([
            'name' => 'Test Seller',
            'phone' => '01000000000',
            'products_commission_rate' => $rate,
            'spare_parts_commission_rate' => $rate,
            'maintenance_parts_commission_rate' => $rate,
            'bikes_for_sale_commission_rate' => $rate,
            'maintenance_services_commission_rate' => $rate,
            ...$overrides,
        ]);
    }
}
