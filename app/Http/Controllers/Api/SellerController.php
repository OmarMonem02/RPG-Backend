<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRequest;
use App\Models\Sale;
use App\Models\Seller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $query = Seller::query()->search($request->query('search'));
        [$from, $to] = $this->currentMonthRange();
        $summary = $this->summarizeSellers(clone $query, $from, $to);

        $sellers = $this->applySort($query, (string) $request->query('sort', 'newest'))
            ->paginate($perPage)
            ->withQueryString();

        $metrics = $this->sellerMetrics($sellers->getCollection()->pluck('id')->all(), $from, $to);
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
        [$from, $to] = $this->currentMonthRange();
        $metrics = $this->sellerMetrics([$seller->id], $from, $to);

        return response()->json($this->serializeSeller($seller, $metrics[$seller->id] ?? null));
    }

    public function monthlyHistory(Request $request, Seller $seller): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);

        if ($year < 2000 || $year > 2100) {
            throw ValidationException::withMessages([
                'year' => ['The year must be between 2000 and 2100.'],
            ]);
        }

        $currentPeriod = now()->format('Y-m');
        $rate = (float) $seller->commission_rate;
        $rows = $this->sellerMetricsByMonth($seller->id, $year);

        $months = [];
        $yearTotals = [
            'completed_sales_count' => 0,
            'commission_base' => 0.0,
            'commission_amount' => 0.0,
        ];

        for ($month = 1; $month <= 12; $month++) {
            $period = sprintf('%04d-%02d', $year, $month);
            $metrics = $rows[$period] ?? [
                'completed_sales_count' => 0,
                'commission_base' => 0.0,
            ];
            $base = (float) $metrics['commission_base'];
            $count = (int) $metrics['completed_sales_count'];
            $amount = $base * ($rate / 100);

            $months[] = [
                'period' => $period,
                'label' => Carbon::create($year, $month, 1)->format('F Y'),
                'completed_sales_count' => $count,
                'commission_base' => round($base, 2),
                'commission_amount' => round($amount, 2),
                'is_current' => $period === $currentPeriod,
            ];

            $yearTotals['completed_sales_count'] += $count;
            $yearTotals['commission_base'] += $base;
            $yearTotals['commission_amount'] += $amount;
        }

        $yearTotals['commission_base'] = round($yearTotals['commission_base'], 2);
        $yearTotals['commission_amount'] = round($yearTotals['commission_amount'], 2);

        return response()->json([
            'seller' => [
                'id' => $seller->id,
                'name' => $seller->name,
                'phone' => $seller->phone,
                'commission_rate' => $rate,
            ],
            'year' => $year,
            'current_period' => $currentPeriod,
            'months' => $months,
            'year_totals' => $yearTotals,
        ]);
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

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentMonthRange(): array
    {
        $now = now();

        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
    }

    private function summarizeSellers(Builder $query, Carbon $from, Carbon $to): array
    {
        $sellers = $query->get(['id', 'phone', 'commission_rate']);
        $metrics = $this->sellerMetrics($sellers->pluck('id')->all(), $from, $to);

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

    private function lineSubtotalSql(): string
    {
        $remainingQty = 'CASE WHEN (sale_items.qty - sale_items.returned_qty) > 0 THEN (sale_items.qty - sale_items.returned_qty) ELSE 0 END';
        $rawSubtotal = "((sale_items.selling_price - sale_items.discount) * {$remainingQty})";

        return "CASE WHEN sale_items.id IS NULL THEN 0 WHEN {$rawSubtotal} > 0 THEN {$rawSubtotal} ELSE 0 END";
    }

    private function applySaleDateRange(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        if ($from) {
            $query->where('sales.created_at', '>=', $from->copy()->startOfDay());
        }

        if ($to) {
            $query->where('sales.created_at', '<=', $to->copy()->endOfDay());
        }

        return $query;
    }

    private function sellerMetrics(array $sellerIds, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if (empty($sellerIds)) {
            return [];
        }

        $lineSubtotal = $this->lineSubtotalSql();

        $query = Sale::query()
            ->leftJoin('sale_items', function ($join) {
                $join
                    ->on('sale_items.sale_id', '=', 'sales.id')
                    ->whereNull('sale_items.deleted_at');
            })
            ->whereIn('sales.seller_id', $sellerIds)
            ->where('sales.status', Sale::STATUS_COMPLETED);

        $this->applySaleDateRange($query, $from, $to);

        return $query
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

    /**
     * @return array<string, array{completed_sales_count: int, commission_base: float}>
     */
    private function sellerMetricsByMonth(int $sellerId, int $year): array
    {
        $lineSubtotal = $this->lineSubtotalSql();
        $driver = DB::connection()->getDriverName();
        $periodExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', sales.created_at)"
            : "DATE_FORMAT(sales.created_at, '%Y-%m')";

        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();

        $rows = Sale::query()
            ->leftJoin('sale_items', function ($join) {
                $join
                    ->on('sale_items.sale_id', '=', 'sales.id')
                    ->whereNull('sale_items.deleted_at');
            })
            ->where('sales.seller_id', $sellerId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw("{$periodExpression} as period")
            ->selectRaw('COUNT(DISTINCT sales.id) as completed_sales_count')
            ->selectRaw("COALESCE(SUM({$lineSubtotal}), 0) as commission_base")
            ->groupBy('period')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [
            (string) $row->period => [
                'completed_sales_count' => (int) $row->completed_sales_count,
                'commission_base' => (float) $row->commission_base,
            ],
        ])->all();
    }

    private function serializeSeller(Seller $seller, ?array $metrics = null): array
    {
        if ($metrics) {
            $completedSalesCount = (int) $metrics['completed_sales_count'];
            $commissionBase = (float) $metrics['commission_base'];
        } else {
            [$from, $to] = $this->currentMonthRange();
            $monthMetrics = $this->sellerMetrics([$seller->id], $from, $to);
            $fallback = $monthMetrics[$seller->id] ?? [
                'completed_sales_count' => 0,
                'commission_base' => 0,
            ];
            $completedSalesCount = (int) $fallback['completed_sales_count'];
            $commissionBase = (float) $fallback['commission_base'];
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

}
