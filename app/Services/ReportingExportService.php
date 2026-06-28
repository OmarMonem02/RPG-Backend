<?php

namespace App\Services;

use App\Models\Expense;

class ReportingExportService
{
    private const CURRENCY = 'EGP';

    public function __construct(private readonly ReportingService $reportingService)
    {
    }

    /**
     * @return array{headings: list<string>, rows: array<int, list<mixed>>, title: string}
     */
    public function profitLoss(array $filters): array
    {
        $report = $this->reportingService->profitLoss($filters);
        $section = $report['currencies'][self::CURRENCY] ?? null;

        if ($section === null) {
            return $this->emptyExport('Profit & Loss', ['Metric', 'Amount (EGP)']);
        }

        $rows = [
            ['Revenue', $section['revenue']],
            ['COGS', $section['cogs']],
            ['Gross Profit', $section['gross_profit']],
            ['Operating Expenses', $section['operating_expenses']],
            ['Net Profit', $section['net_profit']],
        ];

        foreach ($section['revenue_by_type'] ?? [] as $type => $amount) {
            if ((float) $amount > 0) {
                $rows[] = ['Revenue: ' . $type, $amount];
            }
        }

        foreach ($section['expense_categories'] ?? [] as $category => $amount) {
            if ((float) $amount > 0) {
                $rows[] = ['Expense: ' . str_replace('_', ' ', $category), $amount];
            }
        }

        return [
            'headings' => ['Metric', 'Amount (EGP)'],
            'rows' => $rows,
            'title' => 'Profit & Loss',
        ];
    }

    /**
     * @return array{headings: list<string>, rows: array<int, list<mixed>>, title: string}
     */
    public function balanceSheet(array $filters): array
    {
        $report = $this->reportingService->balanceSheet($filters);
        $section = $report['currencies'][self::CURRENCY] ?? null;

        if ($section === null) {
            return $this->emptyExport('Balance Sheet', ['Metric', 'Amount (EGP)']);
        }

        $assets = $section['assets'];
        $liabilities = $section['liabilities'];

        $rows = [
            ['Total Assets', $assets['total_assets']],
            ['Cash Equivalents', $assets['cash_equivalents']['total']],
            ['Accounts Receivable', $assets['accounts_receivable']],
            ['Inventory Total', $assets['inventory']['total']],
            ['Inventory: Products', $assets['inventory']['products']],
            ['Inventory: Spare Parts', $assets['inventory']['spare_parts']],
            ['Inventory: Bikes', $assets['inventory']['bikes']],
            ['Total Liabilities', $liabilities['total_liabilities']],
            ['Equity', $section['equity']],
        ];

        foreach ($assets['cash_equivalents']['payment_methods'] ?? [] as $method => $amount) {
            if ((float) $amount > 0) {
                $rows[] = ['Collected: ' . $method, $amount];
            }
        }

        foreach ($liabilities['expense_categories'] ?? [] as $category => $amount) {
            if ((float) $amount > 0) {
                $rows[] = ['Unpaid: ' . str_replace('_', ' ', $category), $amount];
            }
        }

        return [
            'headings' => ['Metric', 'Amount (EGP)'],
            'rows' => $rows,
            'title' => 'Balance Sheet',
        ];
    }

    /**
     * @return array{headings: list<string>, rows: array<int, list<mixed>>, title: string}
     */
    public function annualSummary(array $filters): array
    {
        $report = $this->reportingService->annualSummary($filters);
        $section = $report['currencies'][self::CURRENCY] ?? null;

        if ($section === null) {
            return $this->emptyExport('Annual Summary', [
                'Month',
                'Revenue',
                'COGS',
                'Gross Profit',
                'Expenses',
                'Net Profit',
            ]);
        }

        $rows = [];
        foreach ($section['monthly'] as $month) {
            $rows[] = [
                $month['month'],
                $month['revenue'],
                $month['cogs'],
                $month['gross_profit'],
                $month['expenses'],
                $month['net_profit'],
            ];
        }

        $totals = $section['totals'];
        $rows[] = [
            'TOTAL',
            $totals['revenue'],
            $totals['cogs'],
            $totals['gross_profit'],
            $totals['expenses'],
            $totals['net_profit'],
        ];

        return [
            'headings' => ['Month', 'Revenue', 'COGS', 'Gross Profit', 'Expenses', 'Net Profit'],
            'rows' => $rows,
            'title' => 'Annual Summary',
        ];
    }

    /**
     * @return array{headings: list<string>, rows: array<int, list<mixed>>, title: string}
     */
    public function expenses(array $filters): array
    {
        $filters['per_page'] = 5000;
        $report = $this->reportingService->expensesSummary($filters);

        $rows = [];
        foreach ($report['data'] as $expense) {
            /** @var Expense $expense */
            $rows[] = [
                $expense->title,
                $expense->category,
                $expense->amount,
                $expense->currency,
                $expense->payment_status,
                optional($expense->incurred_on)->toDateString(),
                optional($expense->due_date)->toDateString(),
                optional($expense->paid_at)?->toDateString(),
                $expense->notes,
            ];
        }

        return [
            'headings' => [
                'Title',
                'Category',
                'Amount',
                'Currency',
                'Payment Status',
                'Incurred On',
                'Due Date',
                'Paid At',
                'Notes',
            ],
            'rows' => $rows,
            'title' => 'Expenses',
        ];
    }

    /**
     * @return array{headings: list<string>, rows: array<int, list<mixed>>, title: string}
     */
    public function overview(array $filters): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $annualFilters = array_merge($filters, ['year' => $year]);

        $pnl = $this->reportingService->profitLoss($filters);
        $balance = $this->reportingService->balanceSheet($filters);
        $annual = $this->reportingService->annualSummary($annualFilters);
        $expenses = $this->reportingService->expensesSummary($filters);

        $pnlSection = $pnl['currencies'][self::CURRENCY] ?? [];
        $balanceSection = $balance['currencies'][self::CURRENCY] ?? [];
        $annualSection = $annual['currencies'][self::CURRENCY] ?? [];
        $expenseSummary = $expenses['summary'][self::CURRENCY] ?? ['total' => 0];

        $rows = [
            ['Net Profit', $pnlSection['net_profit'] ?? 0],
            ['Total Assets', $balanceSection['assets']['total_assets'] ?? 0],
            ['Liabilities', $balanceSection['liabilities']['total_liabilities'] ?? 0],
            ['Annual Margin %', $annualSection['margin_percent'] ?? 0],
            ['Total Expenses', $expenseSummary['total'] ?? 0],
            ['Annual Revenue', $annualSection['totals']['revenue'] ?? 0],
            ['Annual Net Profit', $annualSection['totals']['net_profit'] ?? 0],
        ];

        return [
            'headings' => ['Metric', 'Value (EGP)'],
            'rows' => $rows,
            'title' => 'Overview',
        ];
    }

    /**
     * @param  list<string>  $headings
     * @return array{headings: list<string>, rows: array<int, list<mixed>>, title: string}
     */
    private function emptyExport(string $title, array $headings): array
    {
        return [
            'headings' => $headings,
            'rows' => [],
            'title' => $title,
        ];
    }
}
