<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\UserPermissions;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function meta(): JsonResponse
    {
        return response()->json(UserPermissions::metadata());
    }
}
