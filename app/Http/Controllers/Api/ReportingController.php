<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportingSimpleExport;
use App\Http\Controllers\Controller;
use App\Services\ReportingExportService;
use App\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportingController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService,
        private readonly ReportingExportService $reportingExportService,
    ) {
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

    public function exportOverview(Request $request): Response
    {
        return $this->downloadExport(
            $this->reportingExportService->overview($request->all()),
            'reporting-overview',
            $request->query('format', 'xlsx'),
        );
    }

    public function exportProfitLoss(Request $request): Response
    {
        return $this->downloadExport(
            $this->reportingExportService->profitLoss($request->all()),
            'profit-loss',
            $request->query('format', 'xlsx'),
        );
    }

    public function exportBalanceSheet(Request $request): Response
    {
        return $this->downloadExport(
            $this->reportingExportService->balanceSheet($request->all()),
            'balance-sheet',
            $request->query('format', 'xlsx'),
        );
    }

    public function exportAnnualSummary(Request $request): Response
    {
        return $this->downloadExport(
            $this->reportingExportService->annualSummary($request->all()),
            'annual-summary',
            $request->query('format', 'xlsx'),
        );
    }

    public function exportExpenses(Request $request): Response
    {
        return $this->downloadExport(
            $this->reportingExportService->expenses($request->all()),
            'expenses',
            $request->query('format', 'xlsx'),
        );
    }

    /**
     * @param  array{headings: list<string>, rows: array<int, list<mixed>>, title: string}  $payload
     */
    private function downloadExport(array $payload, string $basename, string $format): Response
    {
        $resolvedFormat = match (strtolower($format)) {
            'csv' => ExcelFormat::CSV,
            default => ExcelFormat::XLSX,
        };

        $extension = $resolvedFormat === ExcelFormat::CSV ? 'csv' : 'xlsx';
        $filename = $basename . '-' . now()->format('Y-m-d') . '.' . $extension;

        $export = new ReportingSimpleExport(
            $payload['headings'],
            $payload['rows'],
            $payload['title'],
        );

        $content = Excel::raw($export, $resolvedFormat);

        return response($content, Response::HTTP_OK, [
            'Content-Type' => $resolvedFormat === ExcelFormat::CSV
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
