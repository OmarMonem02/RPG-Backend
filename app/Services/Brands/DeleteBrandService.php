<?php

namespace App\Services\Brands;

use App\Models\Brand;
use Illuminate\Validation\ValidationException;

class DeleteBrandService
{
    public function execute(Brand $brand): void
    {
        if ($brand->products()->exists()) {
            throw ValidationException::withMessages([
                'brand' => 'Brand cannot be deleted while linked to products.',
            ]);
        }

        $brand->delete();
    }
}
