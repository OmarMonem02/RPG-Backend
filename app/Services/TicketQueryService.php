<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketItem;
use App\Support\CaseInsensitiveLike;
use Illuminate\Database\Eloquent\Builder;

class TicketQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyTicketFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['has_unstored_items'])) {
            $query->whereHas('items', fn (Builder $items) => $items->where('is_unstored', true));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $ticketQuery) use ($search): void {
                if (is_numeric($search)) {
                    $ticketQuery->where('id', (int) $search);
                }

                $ticketQuery
                    ->orWhereHas('customer', function (Builder $customer) use ($search) {
                        CaseInsensitiveLike::where($customer, 'name', $search);
                        CaseInsensitiveLike::orWhere($customer, 'phone', $search);
                    })
                    ->orWhereHas('items', function (Builder $items) use ($search) {
                        $items->where('is_unstored', true)
                            ->where(function (Builder $uncat) use ($search) {
                                CaseInsensitiveLike::where($uncat, 'custom_name', $search);
                                CaseInsensitiveLike::orWhere($uncat, 'custom_description', $search);
                            });
                    });
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportUnstoredItemsQuery(array $filters): Builder
    {
        $ticketFilters = $filters;
        unset($ticketFilters['has_unstored_items']);

        return TicketItem::query()
            ->where('is_unstored', true)
            ->whereHas('ticket', function (Builder $ticketQuery) use ($ticketFilters): void {
                $this->applyTicketFilters($ticketQuery, $ticketFilters);
            })
            ->with(['ticket.customer', 'task'])
            ->orderByDesc('ticket_id')
            ->orderByDesc('id');
    }
}
