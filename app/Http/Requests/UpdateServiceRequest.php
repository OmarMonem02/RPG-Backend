<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'max_discount_type' => ['sometimes', 'required', 'string', Rule::in([
                Service::DISCOUNT_TYPE_PERCENTAGE,
                Service::DISCOUNT_TYPE_FIXED,
            ])],
            'max_discount_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $service = $this->route('service');
            $categoryId = $this->input('category_id', $service?->category_id);
            $category = Category::query()->find($categoryId);

            if ($category !== null && $category->type !== Category::TYPE_SERVICE) {
                $validator->errors()->add('category_id', 'Services must belong to a service category.');
            }

            $type = $this->input('max_discount_type', $service?->max_discount_type);
            $value = (float) $this->input('max_discount_value', $service?->max_discount_value);
            $price = (float) $this->input('price', $service?->price);

            if ($type === Service::DISCOUNT_TYPE_PERCENTAGE && $value > 100) {
                $validator->errors()->add('max_discount_value', 'Percentage discount cannot exceed 100.');
            }

            if ($type === Service::DISCOUNT_TYPE_FIXED && $value > $price) {
                $validator->errors()->add('max_discount_value', 'Fixed discount cannot exceed the service price.');
            }
        });
    }
}
