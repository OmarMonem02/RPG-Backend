<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function __construct(private readonly ReportingService $reportingService)
    {
    }

    public function profitLoss(Request $request): JsonResponse
    {
        return response()->json($this->reportingService->profitLoss($request->all()));
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        return response()->json($this->reportingService->balanceSheet($request->all()));
    }

    public function annualSummary(Request $request): JsonResponse
    {
        return response()->json($this->reportingService->annualSummary($request->all()));
    }

    public function expenses(Request $request): JsonResponse
    {
        return response()->json($this->reportingService->expensesSummary($request->all()));
    }
}
