<?php

namespace App\Services\BikeInventory;

use App\Models\Bike;
use App\Models\BikeInventory;

class CreateBikeInventoryService
{
    public function execute(array $data): BikeInventory
    {
        $bike = isset($data['bike_id']) ? Bike::query()->findOrFail($data['bike_id']) : null;

        return BikeInventory::query()->create([
            'bike_id' => $data['bike_id'] ?? null,
            'type' => $data['type'],
            'brand' => $bike?->brand ?? $data['brand'] ?? null,
            'model' => $bike?->model ?? $data['model'] ?? null,
            'year' => $bike?->year ?? $data['year'] ?? null,
            'cost_price' => $data['cost_price'],
            'selling_price' => $data['selling_price'],
            'mileage' => $data['mileage'] ?? null,
            'cc' => $data['cc'] ?? null,
            'horse_power' => $data['horse_power'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'owner_phone' => $data['owner_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->fresh(['bike']);
    }
}
