<?php

namespace App\Services\Sales;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSalesService
{
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $completedPaymentsSubquery = '(SELECT COALESCE(SUM(payments.amount), 0) FROM payments WHERE payments.sale_id = sales.id AND payments.status = "'.Payment::STATUS_COMPLETED.'")';
        $finalAmountExpression = '(sales.total - sales.discount)';

        $allowedSorts = [
            'id',
            'created_at',
            'updated_at',
            'total',
            'final_amount',
            'paid_amount',
            'remaining_amount',
        ];

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $query = Sale::query()
            ->with(['customer', 'seller', 'payments'])
            ->withCount('items')
            ->withSum([
                'payments as completed_payments_amount' => fn ($query) => $query->where('status', Payment::STATUS_COMPLETED),
            ], 'amount')
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['customer_id']), fn ($query) => $query->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['seller_id']), fn ($query) => $query->where('seller_id', $filters['seller_id']))
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                function ($query) use ($filters): void {
                    $search = $filters['search'];

                    $query->where(function ($query) use ($search): void {
                        if (is_numeric($search)) {
                            $query->where('sales.id', (int) $search)
                                ->orWhereHas('customer', function ($query) use ($search): void {
                                    $query->where('name', 'like', '%'.$search.'%')
                                        ->orWhere('phone', 'like', '%'.$search.'%');
                                })
                                ->orWhereHas('seller', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                                ->orWhereHas('invoice', fn ($query) => $query->where('invoice_number', 'like', '%'.$search.'%'));

                            return;
                        }

                        $query->whereHas('customer', function ($query) use ($search): void {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('phone', 'like', '%'.$search.'%');
                        })
                            ->orWhereHas('seller', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('invoice', fn ($query) => $query->where('invoice_number', 'like', '%'.$search.'%'));
                    });
                }
            )
            ->when(
                ($filters['sale_source'] ?? null) === 'seller_based',
                fn ($query) => $query->whereNotNull('seller_id')
            )
            ->when(
                ($filters['sale_source'] ?? null) === 'direct',
                fn ($query) => $query->whereNull('seller_id')
            )
            ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->when(
                isset($filters['payment_status']),
                function ($query) use ($filters, $completedPaymentsSubquery, $finalAmountExpression): void {
                    match ($filters['payment_status']) {
                        'unpaid' => $query->whereRaw($completedPaymentsSubquery.' <= 0'),
                        'partial' => $query->whereRaw($completedPaymentsSubquery.' > 0 AND '.$completedPaymentsSubquery.' < '.$finalAmountExpression),
                        'paid' => $query->whereRaw($completedPaymentsSubquery.' >= '.$finalAmountExpression.' AND '.$completedPaymentsSubquery.' > 0'),
                        default => null,
                    };
                }
            );

        $sortColumn = match ($sortBy) {
            'final_amount' => $finalAmountExpression,
            'paid_amount' => $completedPaymentsSubquery,
            'remaining_amount' => $finalAmountExpression.' - '.$completedPaymentsSubquery,
            default => 'sales.'.$sortBy,
        };

        if (in_array($sortBy, ['final_amount', 'paid_amount', 'remaining_amount'], true)) {
            $query->orderByRaw($sortColumn.' '.$sortDirection);
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        return $query
            ->orderByDesc('sales.id')
            ->paginate($filters['per_page'] ?? 15);
    }
}
