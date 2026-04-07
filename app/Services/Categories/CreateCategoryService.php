<?php

namespace App\Services\Categories;

use App\Models\Category;

class CreateCategoryService
{
    public function execute(array $data): Category
    {
        return Category::query()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
        ]);
    }
}
