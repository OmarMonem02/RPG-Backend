<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories')->where(
                fn ($query) => $query->where('type', $this->string('type')->value())
            )],
            'type' => ['required', 'string', Rule::in([
                Category::TYPE_PART,
                Category::TYPE_ACCESSORY,
                Category::TYPE_SERVICE,
            ])],
            'description' => ['nullable', 'string'],
        ];
    }
}
