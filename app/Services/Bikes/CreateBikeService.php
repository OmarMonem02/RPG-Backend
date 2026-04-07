<?php

namespace App\Services\Bikes;

use App\Models\Bike;

class CreateBikeService
{
    public function execute(array $data): Bike
    {
        return Bike::query()->create([
            'brand' => $data['brand'],
            'model' => $data['model'],
            'year' => $data['year'],
        ]);
    }
}
