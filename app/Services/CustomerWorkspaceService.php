<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\Sale;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerWorkspaceService
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {
    }

    /**
     * @param  array{search?: string|null, page?: int|null, per_page?: int|null}  $validated
     */
    public function paginateCustomers(array $validated): LengthAwarePaginator
    {
        $query = Customer::query()->search($validated['search'] ?? null);

        $perPage = min(50, max(1, (int) ($validated['per_page'] ?? 20)));
        $page = max(1, (int) ($validated['page'] ?? 1));

        return $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array{sales_page?: int|null, sales_per_page?: int|null, tickets_page?: int|null, tickets_per_page?: int|null}  $validated
     * @return array{customer: array<string, mixed>, stats: array<string, int|float>, bikes: \Illuminate\Support\Collection, sales: LengthAwarePaginator, tickets: LengthAwarePaginator}
     */
    public function buildWorkspace(Customer $customer, array $validated): array
    {
        $customerId = $customer->id;

        $bikes = CustomerBike::query()
            ->where('customer_id', $customerId)
            ->with(['bikeBlueprint.brand'])
            ->orderByDesc('id')
            ->get();

        $salesPage = max(1, (int) ($validated['sales_page'] ?? 1));
        $salesPerPage = min(50, max(1, (int) ($validated['sales_per_page'] ?? 10)));

        $sales = $this->saleService->paginateSales([
            'customer_id' => $customerId,
            'per_page' => $salesPerPage,
            'page' => $salesPage,
            'sort' => 'newest',
        ]);

        $ticketsPage = max(1, (int) ($validated['tickets_page'] ?? 1));
        $ticketsPerPage = min(50, max(1, (int) ($validated['tickets_per_page'] ?? 10)));

        $tickets = Ticket::query()
            ->where('customer_id', $customerId)
            ->with(['customerBike.bikeBlueprint.brand', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($ticketsPerPage, ['*'], 'tickets_page', $ticketsPage);

        $openTickets = Ticket::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $lifetimeTotal = (float) Sale::query()
            ->where('customer_id', $customerId)
            ->sum('total');

        return [
            'customer' => $customer->only([
                'id',
                'name',
                'phone',
                'address',
                'how_did_you_know_us',
                'notes',
                'created_at',
                'updated_at',
            ]),
            'stats' => [
                'bikes_count' => $bikes->count(),
                'tickets_open_count' => $openTickets,
                'sales_count' => (int) Sale::query()->where('customer_id', $customerId)->count(),
                'lifetime_sales_total' => $lifetimeTotal,
            ],
            'bikes' => $bikes,
            'sales' => $sales,
            'tickets' => $tickets,
        ];
    }
}
