<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignPermissionsRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\Users\AssignPermissionsService;
use App\Services\Users\CreateUserService;
use App\Services\Users\UpdateUserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly CreateUserService $createUserService,
        private readonly UpdateUserService $updateUserService,
        private readonly AssignPermissionsService $assignPermissionsService,
    ) {}

    public function index(): JsonResponse
    {
        $users = User::query()->with('permissions')->latest()->get()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->all_permissions,
                'created_at' => $user->created_at,
            ];
        });

        return response()->json([
            'message' => 'Users retrieved successfully.',
            'data' => $users,
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->createUserService->execute($request->validated());

        return response()->json([
            'message' => 'User created successfully.',
            'data' => $user,
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->updateUserService->execute($user, $request->validated());

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user,
        ]);
    }

    public function assignPermissions(AssignPermissionsRequest $request, User $user): JsonResponse
    {
        $user = $this->assignPermissionsService->execute($user, $request->validated()['permissions']);

        return response()->json([
            'message' => 'User permissions updated successfully.',
            'data' => [
                'user' => $user,
                'permissions' => $user->all_permissions,
            ],
        ]);
    }
}
