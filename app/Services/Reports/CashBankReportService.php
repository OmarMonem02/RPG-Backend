<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Payment;
use App\Services\Reports\Concerns\ResolvesReportDateRange;

class CashBankReportService
{
    use ResolvesReportDateRange;

    public function execute(array $filters): array
    {
        ['from' => $from, 'to' => $to] = $this->resolveDateRange($filters);

        $cash = (float) Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->where('method', Payment::METHOD_CASH)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $visa = (float) Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->where('method', Payment::METHOD_VISA)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $instapay = (float) Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->where('method', Payment::METHOD_INSTAPAY)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $cashExpenses = (float) Expense::query()
            ->where('paid_by', Expense::PAID_BY_CASH)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $bankExpenses = (float) Expense::query()
            ->where('paid_by', Expense::PAID_BY_BANK)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $bankInflow = $visa + $instapay;
        $netCash = round($cash - $cashExpenses, 2);
        $netBank = round($bankInflow - $bankExpenses, 2);

        return [
            'period' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
            ],
            'cash' => [
                'inflow' => round($cash, 2),
                'expenses' => round($cashExpenses, 2),
                'net' => $netCash,
            ],
            'bank' => [
                'visa' => round($visa, 2),
                'instapay' => round($instapay, 2),
                'inflow' => round($bankInflow, 2),
                'expenses' => round($bankExpenses, 2),
                'net' => $netBank,
            ],
        ];
    }
}
