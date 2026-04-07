<?php

namespace App\Http\Requests;

use App\Models\BikeInventory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBikeInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bike_id' => ['nullable', 'integer', 'exists:bikes,id'],
            'type' => ['required', 'string', Rule::in([BikeInventory::TYPE_OWNED, BikeInventory::TYPE_CONSIGNMENT])],
            'brand' => ['nullable', 'string', 'max:255', 'required_without:bike_id'],
            'model' => ['nullable', 'string', 'max:255', 'required_without:bike_id'],
            'year' => ['nullable', 'integer', 'between:1900,'.(date('Y') + 1), 'required_without:bike_id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'cc' => ['nullable', 'integer', 'min:0'],
            'horse_power' => ['nullable', 'integer', 'min:0'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') === BikeInventory::TYPE_CONSIGNMENT) {
                if (! $this->filled('owner_name')) {
                    $validator->errors()->add('owner_name', 'Owner name is required for consignment bikes.');
                }

                if (! $this->filled('owner_phone')) {
                    $validator->errors()->add('owner_phone', 'Owner phone is required for consignment bikes.');
                }
            }
        });
    }
}
