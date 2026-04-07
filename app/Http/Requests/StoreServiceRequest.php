<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'max_discount_type' => ['required', 'string', Rule::in([
                Service::DISCOUNT_TYPE_PERCENTAGE,
                Service::DISCOUNT_TYPE_FIXED,
            ])],
            'max_discount_value' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $category = Category::query()->find($this->input('category_id'));

            if ($category !== null && $category->type !== Category::TYPE_SERVICE) {
                $validator->errors()->add('category_id', 'Services must belong to a service category.');
            }

            $type = $this->input('max_discount_type');
            $value = (float) $this->input('max_discount_value', 0);
            $price = (float) $this->input('price', 0);

            if ($type === Service::DISCOUNT_TYPE_PERCENTAGE && $value > 100) {
                $validator->errors()->add('max_discount_value', 'Percentage discount cannot exceed 100.');
            }

            if ($type === Service::DISCOUNT_TYPE_FIXED && $value > $price) {
                $validator->errors()->add('max_discount_value', 'Fixed discount cannot exceed the service price.');
            }
        });
    }
}
