<?php

namespace App\Services\Services;

use App\Models\Service;

class UpdateServiceService
{
    public function execute(Service $service, array $data): Service
    {
        $service->update([
            'category_id' => $data['category_id'] ?? $service->category_id,
            'name' => $data['name'] ?? $service->name,
            'price' => $data['price'] ?? $service->price,
            'max_discount_type' => $data['max_discount_type'] ?? $service->max_discount_type,
            'max_discount_value' => $data['max_discount_value'] ?? $service->max_discount_value,
            'description' => array_key_exists('description', $data) ? $data['description'] : $service->description,
        ]);

        return $service->fresh(['category']);
    }
}
