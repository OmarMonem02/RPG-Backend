<?php

namespace App\Services\Dashboard;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Ticket;
use App\Models\TicketItem;

class DashboardMetricsService
{
    public function execute(): array
    {
        $today = now()->toDateString();

        $salesToday = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->whereDate('sales.created_at', $today)
            ->selectRaw('COALESCE(SUM((sale_items.price_snapshot * sale_items.qty) - sale_items.discount), 0) as total')
            ->value('total');

        $servicesToday = (float) TicketItem::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')
            ->where('ticket_items.item_type', TicketItem::ITEM_TYPE_SERVICE)
            ->whereNull('tickets.deleted_at')
            ->whereDate('tickets.created_at', $today)
            ->selectRaw('COALESCE(SUM(ticket_items.price_snapshot * ticket_items.qty), 0) as total')
            ->value('total');

        $expensesToday = (float) Expense::query()
            ->whereDate('expense_date', $today)
            ->sum('amount');

        $returnsToday = (float) SaleReturn::query()
            ->join('sale_items', 'sale_items.id', '=', 'returns.item_id')
            ->whereDate('returns.created_at', $today)
            ->selectRaw('COALESCE(SUM((sale_items.price_snapshot * returns.qty) - ((sale_items.discount / NULLIF(sale_items.qty, 0)) * returns.qty)), 0) as total')
            ->value('total');

        $salePendingPayments = (float) Invoice::query()
            ->join('sales', 'sales.id', '=', 'invoices.reference_id')
            ->where('invoices.type', Invoice::TYPE_SALE)
            ->whereNull('sales.deleted_at')
            ->selectRaw('COALESCE(SUM(GREATEST(invoices.final_total - (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.sale_id = sales.id AND payments.status = ?), 0)), 0) as total', [Payment::STATUS_COMPLETED])
            ->value('total');

        $ticketPendingPayments = (float) Invoice::query()
            ->join('tickets', 'tickets.id', '=', 'invoices.reference_id')
            ->where('invoices.type', Invoice::TYPE_TICKET)
            ->whereNull('tickets.deleted_at')
            ->selectRaw('COALESCE(SUM(invoices.final_total), 0) as total')
            ->value('total');

        return [
            'total_sales_today' => round($salesToday - $returnsToday, 2),
            'total_profit_today' => round((($salesToday - $returnsToday) + $servicesToday) - $expensesToday, 2),
            'open_tickets_count' => Ticket::query()
                ->where('status', '!=', Ticket::STATUS_COMPLETED)
                ->whereNull('deleted_at')
                ->count(),
            'pending_payments' => round($salePendingPayments + $ticketPendingPayments, 2),
            'total_expenses_today' => round($expensesToday, 2),
        ];
    }
}
