<?php

namespace App\Services\BikeInventory;

use App\Models\Bike;
use App\Models\BikeInventory;
use Illuminate\Validation\ValidationException;

class UpdateBikeInventoryService
{
    public function execute(BikeInventory $bikeInventory, array $data): BikeInventory
    {
        if ($bikeInventory->is_sold) {
            throw ValidationException::withMessages([
                'bike_inventory' => 'Sold bikes cannot be modified.',
            ]);
        }

        $bike = array_key_exists('bike_id', $data) && $data['bike_id'] !== null
            ? Bike::query()->findOrFail($data['bike_id'])
            : null;

        $bikeInventory->update([
            'bike_id' => array_key_exists('bike_id', $data) ? $data['bike_id'] : $bikeInventory->bike_id,
            'type' => $data['type'] ?? $bikeInventory->type,
            'brand' => $bike?->brand ?? $data['brand'] ?? $bikeInventory->brand,
            'model' => $bike?->model ?? $data['model'] ?? $bikeInventory->model,
            'year' => $bike?->year ?? $data['year'] ?? $bikeInventory->year,
            'cost_price' => $data['cost_price'] ?? $bikeInventory->cost_price,
            'selling_price' => $data['selling_price'] ?? $bikeInventory->selling_price,
            'mileage' => array_key_exists('mileage', $data) ? $data['mileage'] : $bikeInventory->mileage,
            'cc' => array_key_exists('cc', $data) ? $data['cc'] : $bikeInventory->cc,
            'horse_power' => array_key_exists('horse_power', $data) ? $data['horse_power'] : $bikeInventory->horse_power,
            'owner_name' => array_key_exists('owner_name', $data) ? $data['owner_name'] : $bikeInventory->owner_name,
            'owner_phone' => array_key_exists('owner_phone', $data) ? $data['owner_phone'] : $bikeInventory->owner_phone,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $bikeInventory->notes,
        ]);

        return $bikeInventory->fresh(['bike', 'saleItems.sale']);
    }
}
