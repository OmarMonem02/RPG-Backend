<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserService
{
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $newRole = $data['role'] ?? $user->role;

            $user->fill([
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
                'role' => $newRole,
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            if ($newRole !== User::ROLE_STAFF) {
                $user->permissions()->sync([]);
            }

            return $user->fresh('permissions');
        });
    }
}
