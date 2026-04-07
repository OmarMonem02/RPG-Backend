<?php

namespace App\Services\Brands;

use App\Models\Brand;

class CreateBrandService
{
    public function execute(array $data): Brand
    {
        return Brand::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }
}
