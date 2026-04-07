<?php

namespace App\Services\BikeInventory;

use App\Models\BikeInventory;
use Illuminate\Validation\ValidationException;

class DeleteBikeInventoryService
{
    public function execute(BikeInventory $bikeInventory): void
    {
        if ($bikeInventory->saleItems()->exists()) {
            throw ValidationException::withMessages([
                'bike_inventory' => 'Bike inventory record cannot be deleted after being linked to a sale.',
            ]);
        }

        $bikeInventory->delete();
    }
}
