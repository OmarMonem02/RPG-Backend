<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $query = Seller::query()->search($request->query('search'));
        $summary = $this->summarizeSellers(clone $query);

        $sellers = $this->applySort($query, (string) $request->query('sort', 'newest'))
            ->paginate($perPage)
            ->withQueryString();

        $metrics = $this->sellerMetrics($sellers->getCollection()->pluck('id')->all());
        $sellers->setCollection(
            $sellers->getCollection()->map(
                fn (Seller $seller) => $this->serializeSeller($seller, $metrics[$seller->id] ?? null)
            )
        );

        $payload = $sellers->toArray();
        $payload['summary'] = $summary;

        return response()->json($payload);
    }

    public function store(SellerRequest $request): JsonResponse
    {
        $seller = Seller::create($request->validated());

        return response()->json($this->serializeSeller($seller), 201);
    }

    public function show(Seller $seller): JsonResponse
    {
        $seller->load([
            'sales' => fn ($query) => $query
                ->where('status', Sale::STATUS_COMPLETED)
                ->with('items'),
        ]);

        return response()->json($this->serializeSeller($seller));
    }

    public function update(SellerRequest $request, Seller $seller): JsonResponse
    {
        $seller->update($request->validated());

        return response()->json($this->serializeSeller($seller));
    }

    public function destroy(Seller $seller): JsonResponse
    {
        $seller->delete();

        return response()->json([], 204);
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'rate_high' => $query->orderByDesc('commission_rate')->orderByDesc('id'),
            'rate_low' => $query->orderBy('commission_rate')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    private function summarizeSellers(Builder $query): array
    {
        $sellers = $query->get(['id', 'phone', 'commission_rate']);
        $metrics = $this->sellerMetrics($sellers->pluck('id')->all());

        $commissionBase = 0;
        $commissionAmount = 0;
        $completedSalesCount = 0;

        foreach ($sellers as $seller) {
            $sellerMetrics = $metrics[$seller->id] ?? [
                'completed_sales_count' => 0,
                'commission_base' => 0,
            ];
            $base = (float) $sellerMetrics['commission_base'];

            $commissionBase += $base;
            $commissionAmount += $base * ((float) $seller->commission_rate / 100);
            $completedSalesCount += (int) $sellerMetrics['completed_sales_count'];
        }

        return [
            'total_sellers' => $sellers->count(),
            'commissioned_sellers' => $sellers->where('commission_rate', '>', 0)->count(),
            'high_commission_sellers' => $sellers->where('commission_rate', '>=', 10)->count(),
            'contact_ready_sellers' => $sellers->filter(fn (Seller $seller) => filled($seller->phone))->count(),
            'completed_sales_count' => $completedSalesCount,
            'commission_base' => round($commissionBase, 2),
            'commission_amount' => round($commissionAmount, 2),
            'average_commission_rate' => round((float) $sellers->avg('commission_rate'), 2),
        ];
    }

    private function sellerMetrics(array $sellerIds): array
    {
        if (empty($sellerIds)) {
            return [];
        }

        $remainingQty = 'CASE WHEN (sale_items.qty - sale_items.returned_qty) > 0 THEN (sale_items.qty - sale_items.returned_qty) ELSE 0 END';
        $rawSubtotal = "((sale_items.selling_price - sale_items.discount) * {$remainingQty})";
        $lineSubtotal = "CASE WHEN sale_items.id IS NULL THEN 0 WHEN {$rawSubtotal} > 0 THEN {$rawSubtotal} ELSE 0 END";

        return Sale::query()
            ->leftJoin('sale_items', function ($join) {
                $join
                    ->on('sale_items.sale_id', '=', 'sales.id')
                    ->whereNull('sale_items.deleted_at');
            })
            ->whereIn('sales.seller_id', $sellerIds)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->selectRaw('sales.seller_id')
            ->selectRaw('COUNT(DISTINCT sales.id) as completed_sales_count')
            ->selectRaw("COALESCE(SUM({$lineSubtotal}), 0) as commission_base")
            ->groupBy('sales.seller_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->seller_id => [
                    'completed_sales_count' => (int) $row->completed_sales_count,
                    'commission_base' => (float) $row->commission_base,
                ],
            ])
            ->all();
    }

    private function serializeSeller(Seller $seller, ?array $metrics = null): array
    {
        if ($metrics) {
            $completedSalesCount = (int) $metrics['completed_sales_count'];
            $commissionBase = (float) $metrics['commission_base'];
        } else {
            $completedSales = $seller->relationLoaded('sales') ? $seller->sales : collect();
            $completedSalesCount = $completedSales->count();
            $commissionBase = $completedSales->sum(
                fn (Sale $sale) => $sale->items->sum(fn (SaleItem $item) => $this->lineSubtotal($item))
            );
        }

        $commissionAmount = $commissionBase * ((float) $seller->commission_rate / 100);

        return [
            'id' => $seller->id,
            'name' => $seller->name,
            'phone' => $seller->phone,
            'commission_rate' => (float) $seller->commission_rate,
            'completed_sales_count' => $completedSalesCount,
            'commission_base' => round($commissionBase, 2),
            'commission_amount' => round($commissionAmount, 2),
            'created_at' => $seller->created_at,
            'updated_at' => $seller->updated_at,
        ];
    }

    private function lineSubtotal(SaleItem $item): float
    {
        $remainingQty = max(0, (int) $item->qty - (int) $item->returned_qty);

        return max(0, ((float) $item->selling_price - (float) $item->discount) * $remainingQty);
    }
}
