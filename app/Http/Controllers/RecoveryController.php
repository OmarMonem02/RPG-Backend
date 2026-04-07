<?php

namespace App\Http\Controllers;

use App\Services\Recovery\SoftDeleteRecoveryService;
use Illuminate\Http\JsonResponse;

class RecoveryController extends Controller
{
    public function __construct(
        private readonly SoftDeleteRecoveryService $softDeleteRecoveryService,
    ) {}

    public function index(string $entity): JsonResponse
    {
        return response()->json([
            'message' => 'Deleted records retrieved successfully.',
            'data' => $this->softDeleteRecoveryService->listDeleted($entity),
        ]);
    }

    public function restore(string $entity, int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Record restored successfully.',
            'data' => $this->softDeleteRecoveryService->restore($entity, $id),
        ]);
    }
}
