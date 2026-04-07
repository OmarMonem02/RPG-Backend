<?php

namespace App\Services\Bikes;

use App\Models\Bike;
use Illuminate\Validation\ValidationException;

class DeleteBikeService
{
    public function execute(Bike $bike): void
    {
        if ($bike->products()->exists() || $bike->inventoryBikes()->exists()) {
            throw ValidationException::withMessages([
                'bike' => 'Bike blueprint cannot be deleted while linked to products or inventory bikes.',
            ]);
        }

        $bike->delete();
    }
}
