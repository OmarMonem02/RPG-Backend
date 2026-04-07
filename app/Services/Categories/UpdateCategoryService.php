<?php

namespace App\Services\Categories;

use App\Models\Category;

class UpdateCategoryService
{
    public function execute(Category $category, array $data): Category
    {
        $category->update([
            'name' => $data['name'] ?? $category->name,
            'type' => $data['type'] ?? $category->type,
            'description' => array_key_exists('description', $data) ? $data['description'] : $category->description,
        ]);

        return $category->fresh();
    }
}
