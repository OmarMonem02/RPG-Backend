<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $dashboardMetricsService,
    ) {
    }

    public function metrics(): JsonResponse
    {
        return response()->json([
            'message' => 'Dashboard metrics retrieved successfully.',
            'data' => $this->dashboardMetricsService->execute(),
        ]);
    }
}
