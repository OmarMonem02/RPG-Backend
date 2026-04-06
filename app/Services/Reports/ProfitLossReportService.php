<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Services\Reports\Concerns\ResolvesReportDateRange;
class ProfitLossReportService
{
    use ResolvesReportDateRange;

    public function execute(array $filters): array
    {
        ['from' => $from, 'to' => $to] = $this->resolveDateRange($filters);

        $salesRevenue = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM((sale_items.price_snapshot * sale_items.qty) - sale_items.discount), 0) as total')
            ->value('total');

        $returnedSalesValue = (float) SaleReturn::query()
            ->join('sale_items', 'sale_items.id', '=', 'returns.item_id')
            ->join('sales', 'sales.id', '=', 'returns.sale_id')
            ->whereBetween('returns.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM((sale_items.price_snapshot * returns.qty) - ((sale_items.discount / NULLIF(sale_items.qty, 0)) * returns.qty)), 0) as total')
            ->value('total');

        $servicesRevenue = (float) TicketItem::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')
            ->where('ticket_items.item_type', TicketItem::ITEM_TYPE_SERVICE)
            ->where('tickets.status', Ticket::STATUS_COMPLETED)
            ->whereNull('tickets.deleted_at')
            ->whereBetween('tickets.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(ticket_items.price_snapshot * ticket_items.qty), 0) as total')
            ->value('total');

        $totalExpenses = (float) Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $totalRevenue = round(($salesRevenue - $returnedSalesValue) + $servicesRevenue, 2);
        $netProfit = round($totalRevenue - $totalExpenses, 2);

        return [
            'period' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
            ],
            'revenue' => [
                'sales' => round($salesRevenue - $returnedSalesValue, 2),
                'services' => round($servicesRevenue, 2),
                'total' => $totalRevenue,
            ],
            'expenses' => [
                'total' => round($totalExpenses, 2),
            ],
            'net_profit' => $netProfit,
        ];
    }
}
