<?php

namespace App\Services\Services;

use App\Models\Service;

class CreateServiceService
{
    public function execute(array $data): Service
    {
        return Service::query()->create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'price' => $data['price'],
            'max_discount_type' => $data['max_discount_type'],
            'max_discount_value' => $data['max_discount_value'],
            'description' => $data['description'] ?? null,
        ])->fresh(['category']);
    }
}
