<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignSparePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'spare_part_id' => 'required_without_all:spare_part_ids|integer|exists:spare_parts,id',
            'spare_part_ids' => 'required_without:spare_part_id|array',
            'spare_part_ids.*' => 'integer|exists:spare_parts,id',
        ];
    }

    public function messages(): array
    {
        return [
            'spare_part_id.exists' => 'Spare part does not exist.',
            'spare_part_ids.*.exists' => 'One or more spare parts do not exist.',
        ];
    }
}
