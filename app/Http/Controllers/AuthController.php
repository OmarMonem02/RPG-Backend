<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\Auth\LoginService;
use App\Services\Auth\LogoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginService $loginService,
        private readonly LogoutService $logoutService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginService->execute($request->validated());

        return response()->json([
            'message' => 'Logged in successfully.',
            'data' => $result,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutService->execute($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('permissions');

        return response()->json([
            'message' => 'Current user retrieved successfully.',
            'data' => [
                'user' => $user,
                'permissions' => $user->all_permissions,
            ],
        ]);
    }
}
