<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

trait ValidatesSaleDiscountAdminPassword
{
    protected function validateSaleDiscountAdminPassword(Validator $validator): void
    {
        if (! $this->has('discount')) {
            return;
        }

        $saleDiscount = (float) ($this->input('discount') ?? 0);
        if ($saleDiscount <= 0) {
            return;
        }

        $user = $this->user();
        if (! $user || $user->role !== User::ROLE_ADMIN) {
            $validator->errors()->add(
                'admin_password',
                'Only administrators can apply an overall sale discount.',
            );

            return;
        }

        $password = $this->input('admin_password');
        if (! is_string($password) || trim($password) === '') {
            $validator->errors()->add(
                'admin_password',
                'Administrator password is required to apply an overall sale discount.',
            );

            return;
        }

        if (! Hash::check($password, $user->password)) {
            $validator->errors()->add(
                'admin_password',
                'Invalid administrator password.',
            );
        }
    }
}
