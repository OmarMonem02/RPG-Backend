<?php

namespace App\Http\Resources;

use App\Support\UserPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'permissions' => $this->effectivePermissions(),
            'role_permissions' => UserPermissions::defaultMatrixForRole((string) $this->role),
            'permission_source' => is_array($this->permissions_override) ? 'custom' : 'role',
        ];
    }
}
