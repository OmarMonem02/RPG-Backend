<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserPermissionsRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::paginate(20));
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['user' => new UserResource($user)]);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([], 204);
    }

    public function updatePermissions(UpdateUserPermissionsRequest $request, User $user): JsonResponse
    {
        $permissions = $request->normalizedPermissions();

        if (
            $request->user()?->is($user)
            && (
                ! in_array('read', $permissions['users'], true)
                || ! in_array('update', $permissions['users'], true)
            )
        ) {
            throw ValidationException::withMessages([
                'permissions.users' => 'Admins must keep users.read and users.update when editing their own permissions.',
            ]);
        }

        $user->forceFill([
            'permissions_override' => $permissions,
        ])->save();

        return response()->json(['user' => new UserResource($user->fresh())]);
    }
}
