<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\Reports\BalanceSheetService;
use App\Services\Reports\CashBankReportService;
use App\Services\Reports\DailyReportService;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\ExportReportService;
use App\Services\Reports\ProfitLossReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ProfitLossReportService $profitLossReportService,
        private readonly BalanceSheetService $balanceSheetService,
        private readonly DailyReportService $dailyReportService,
        private readonly ExpenseReportService $expenseReportService,
        private readonly CashBankReportService $cashBankReportService,
        private readonly ExportReportService $exportReportService,
    ) {
    }

    public function profitLoss(ReportFilterRequest $request): JsonResponse|Response|StreamedResponse
    {
        return $this->respond('profit-loss', $this->profitLossReportService->execute($request->validated()), $request->validated()['format'] ?? 'json');
    }

    public function balanceSheet(ReportFilterRequest $request): JsonResponse|Response|StreamedResponse
    {
        return $this->respond('balance-sheet', $this->balanceSheetService->execute($request->validated()), $request->validated()['format'] ?? 'json');
    }

    public function daily(ReportFilterRequest $request): JsonResponse|Response|StreamedResponse
    {
        return $this->respond('daily-report', $this->dailyReportService->execute($request->validated()), $request->validated()['format'] ?? 'json');
    }

    public function expenses(ReportFilterRequest $request): JsonResponse|Response|StreamedResponse
    {
        return $this->respond('expenses-report', $this->expenseReportService->execute($request->validated()), $request->validated()['format'] ?? 'json');
    }

    public function cashBank(ReportFilterRequest $request): JsonResponse|Response|StreamedResponse
    {
        return $this->respond('cash-bank-report', $this->cashBankReportService->execute($request->validated()), $request->validated()['format'] ?? 'json');
    }

    private function respond(string $reportName, array $data, string $format): JsonResponse|Response|StreamedResponse
    {
        if ($format === 'json') {
            return response()->json([
                'message' => 'Report generated successfully.',
                'data' => $data,
            ]);
        }

        return $this->exportReportService->export($reportName, $data, $format);
    }
}
