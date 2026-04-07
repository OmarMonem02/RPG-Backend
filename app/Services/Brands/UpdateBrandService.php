<?php

namespace App\Services\Brands;

use App\Models\Brand;

class UpdateBrandService
{
    public function execute(Brand $brand, array $data): Brand
    {
        $brand->update([
            'name' => $data['name'] ?? $brand->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $brand->description,
        ]);

        return $brand->fresh();
    }
}
