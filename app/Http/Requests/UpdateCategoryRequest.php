<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id ?? $this->route('category');
        $type = $this->input('type', $this->route('category')?->type);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories')->ignore($categoryId)->where(
                fn ($query) => $query->where('type', $type)
            )],
            'type' => ['sometimes', 'required', 'string', Rule::in([
                Category::TYPE_PART,
                Category::TYPE_ACCESSORY,
                Category::TYPE_SERVICE,
            ])],
            'description' => ['nullable', 'string'],
        ];
    }
}
