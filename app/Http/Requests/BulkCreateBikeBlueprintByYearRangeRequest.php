<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkCreateBikeBlueprintByYearRangeRequest extends FormRequest
{
    public const MAX_YEAR_SPAN = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxYear = (int) date('Y') + 10;

        return [
            'brand_id' => 'required|integer|exists:brands,id',
            'model' => 'required|string|max:255',
            'year_from' => 'required|integer|min:1900|max:'.$maxYear,
            'year_to' => 'required|integer|gte:year_from|max:'.$maxYear,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $yearFrom = (int) $this->input('year_from');
            $yearTo = (int) $this->input('year_to');

            if ($yearFrom > 0 && $yearTo > 0 && ($yearTo - $yearFrom + 1) > self::MAX_YEAR_SPAN) {
                $validator->errors()->add(
                    'year_to',
                    'Year range cannot exceed '.self::MAX_YEAR_SPAN.' years.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'brand_id.exists' => 'Selected brand does not exist.',
            'year_from.max' => 'Year cannot be more than 10 years in the future.',
            'year_to.max' => 'Year cannot be more than 10 years in the future.',
            'year_to.gte' => 'Year To must be greater than or equal to Year From.',
        ];
    }
}
