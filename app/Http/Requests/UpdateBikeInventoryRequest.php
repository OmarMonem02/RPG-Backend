<?php

namespace App\Http\Requests;

use App\Models\BikeInventory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBikeInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bike_id' => ['sometimes', 'nullable', 'integer', 'exists:bikes,id'],
            'type' => ['sometimes', 'required', 'string', Rule::in([BikeInventory::TYPE_OWNED, BikeInventory::TYPE_CONSIGNMENT])],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:1900,'.(date('Y') + 1)],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
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
            $record = $this->route('bikeInventory');
            $type = $this->input('type', $record?->type);

            if ($type === BikeInventory::TYPE_CONSIGNMENT) {
                $ownerName = $this->input('owner_name', $record?->owner_name);
                $ownerPhone = $this->input('owner_phone', $record?->owner_phone);

                if (blank($ownerName)) {
                    $validator->errors()->add('owner_name', 'Owner name is required for consignment bikes.');
                }

                if (blank($ownerPhone)) {
                    $validator->errors()->add('owner_phone', 'Owner phone is required for consignment bikes.');
                }
            }
        });
    }
}
