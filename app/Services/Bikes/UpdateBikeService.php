<?php

namespace App\Services\Bikes;

use App\Models\Bike;

class UpdateBikeService
{
    public function execute(Bike $bike, array $data): Bike
    {
        $bike->update([
            'brand' => $data['brand'] ?? $bike->brand,
            'model' => $data['model'] ?? $bike->model,
            'year' => $data['year'] ?? $bike->year,
        ]);

        return $bike->fresh();
    }
}
