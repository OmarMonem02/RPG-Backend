<?php

namespace App\Services\Users;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignPermissionsService
{
    public function execute(User $user, array $permissionNames): User
    {
        return DB::transaction(function () use ($user, $permissionNames): User {
            if ($user->role !== User::ROLE_STAFF) {
                throw ValidationException::withMessages([
                    'user' => 'Direct permission overrides can only be assigned to staff users.',
                ]);
            }

            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id')
                ->all();

            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->permissions()->sync($permissionIds);

            return $user->fresh('permissions');
        });
    }
}
