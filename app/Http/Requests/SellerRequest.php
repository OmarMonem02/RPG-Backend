<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rateRules = ['required', 'numeric', 'min:0'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'products_commission_rate' => $rateRules,
            'spare_parts_commission_rate' => $rateRules,
            'maintenance_parts_commission_rate' => $rateRules,
            'bikes_for_sale_commission_rate' => $rateRules,
            'maintenance_services_commission_rate' => $rateRules,
        ];
    }
}
