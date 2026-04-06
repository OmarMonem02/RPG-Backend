<?php

namespace App\Services\Reports;

class DailyReportService
{
    public function __construct(
        private readonly ProfitLossReportService $profitLossReportService,
    ) {
    }

    public function execute(array $filters): array
    {
        $report = $this->profitLossReportService->execute([
            'date' => $filters['date'] ?? $filters['from_date'] ?? now()->toDateString(),
        ]);

        return [
            'date' => $report['period']['from_date'],
            'sales' => $report['revenue']['sales'],
            'services' => $report['revenue']['services'],
            'expenses' => $report['expenses']['total'],
            'profit' => $report['net_profit'],
        ];
    }
}
