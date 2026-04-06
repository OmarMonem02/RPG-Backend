<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Services\Reports\Concerns\ResolvesReportDateRange;

class BalanceSheetService
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

        $bank = (float) Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereIn('method', [Payment::METHOD_VISA, Payment::METHOD_INSTAPAY])
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $cashExpenseOutflow = (float) Expense::query()
            ->where('paid_by', Expense::PAID_BY_CASH)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $bankExpenseOutflow = (float) Expense::query()
            ->where('paid_by', Expense::PAID_BY_BANK)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $inventoryValue = (float) Product::query()
            ->selectRaw('COALESCE(SUM(cost_price * qty), 0) as total')
            ->value('total');

        $paymentSubquery = Payment::query()
            ->select('sale_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as paid_total')
            ->where('status', Payment::STATUS_COMPLETED)
            ->groupBy('sale_id');

        $salesReceivables = (float) Sale::query()
            ->leftJoinSub($paymentSubquery, 'payment_totals', function ($join): void {
                $join->on('payment_totals.sale_id', '=', 'sales.id');
            })
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(GREATEST((sales.total - sales.discount) - COALESCE(payment_totals.paid_total, 0), 0)), 0) as total')
            ->value('total');

        $servicesReceivables = (float) TicketItem::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')
            ->where('ticket_items.item_type', TicketItem::ITEM_TYPE_SERVICE)
            ->where('tickets.status', Ticket::STATUS_COMPLETED)
            ->whereNull('tickets.deleted_at')
            ->whereBetween('tickets.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(ticket_items.price_snapshot * ticket_items.qty), 0) as total')
            ->value('total');

        $receivables = round($salesReceivables + $servicesReceivables, 2);
        $netCash = round($cash - $cashExpenseOutflow, 2);
        $netBank = round($bank - $bankExpenseOutflow, 2);
        $totalAssets = round($netCash + $netBank + $inventoryValue + $receivables, 2);

        return [
            'period' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
            ],
            'assets' => [
                'cash' => $netCash,
                'bank' => $netBank,
                'inventory_value' => round($inventoryValue, 2),
                'receivables' => $receivables,
                'total_assets' => $totalAssets,
            ],
            'liabilities' => [
                'total' => 0.0,
            ],
        ];
    }
}
