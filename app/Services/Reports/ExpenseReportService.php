<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Services\Reports\Concerns\ResolvesReportDateRange;

class ExpenseReportService
{
    use ResolvesReportDateRange;

    public function execute(array $filters): array
    {
        ['from' => $from, 'to' => $to] = $this->resolveDateRange($filters);

        $rows = Expense::query()
            ->selectRaw('category, COALESCE(SUM(amount), 0) as total')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();

        return [
            'period' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
            ],
            'expenses' => $rows,
            'total' => round(collect($rows)->sum('total'), 2),
        ];
    }
}
