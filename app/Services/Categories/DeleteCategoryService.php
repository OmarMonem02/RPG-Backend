<?php

namespace App\Services\Categories;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class DeleteCategoryService
{
    public function execute(Category $category): void
    {
        if ($category->products()->exists() || $category->services()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Category cannot be deleted while linked to products or services.',
            ]);
        }

        $category->delete();
    }
}
