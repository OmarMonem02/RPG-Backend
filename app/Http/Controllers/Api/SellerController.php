<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRequest;
use App\Models\Sale;
use App\Models\Seller;
use App\Services\SaleCommissionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    public function __construct(private readonly SaleCommissionService $commissionService)
    {
    }

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
                'commission_amount' => 0.0,
            ];
            $base = (float) $metrics['commission_base'];
            $amount = (float) $metrics['commission_amount'];
            $count = (int) $metrics['completed_sales_count'];

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
            'seller' => $this->serializeSellerRates($seller),
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
        $maxRate = $this->commissionService->maxRateOrderExpression();

        return match ($sort) {
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'rate_high' => $query->orderByRaw("{$maxRate} DESC")->orderByDesc('id'),
            'rate_low' => $query->orderByRaw("{$maxRate} ASC")->orderByDesc('id'),
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
        $sellers = $query->get();
        $metrics = $this->sellerMetrics($sellers->pluck('id')->all(), $from, $to);

        $commissionBase = 0;
        $commissionAmount = 0;
        $completedSalesCount = 0;
        $rateSum = 0.0;

        foreach ($sellers as $seller) {
            $sellerMetrics = $metrics[$seller->id] ?? [
                'completed_sales_count' => 0,
                'commission_base' => 0,
                'commission_amount' => 0,
            ];

            $commissionBase += (float) $sellerMetrics['commission_base'];
            $commissionAmount += (float) $sellerMetrics['commission_amount'];
            $completedSalesCount += (int) $sellerMetrics['completed_sales_count'];
            $rateSum += $this->commissionService->sellerAverageRate($seller);
        }

        return [
            'total_sellers' => $sellers->count(),
            'commissioned_sellers' => $sellers->filter(fn (Seller $seller) => $this->commissionService->sellerHasCommission($seller))->count(),
            'high_commission_sellers' => $sellers->filter(fn (Seller $seller) => $this->commissionService->sellerHasHighCommission($seller))->count(),
            'contact_ready_sellers' => $sellers->filter(fn (Seller $seller) => filled($seller->phone))->count(),
            'completed_sales_count' => $completedSalesCount,
            'commission_base' => round($commissionBase, 2),
            'commission_amount' => round($commissionAmount, 2),
            'average_commission_rate' => $sellers->isEmpty() ? 0 : round($rateSum / $sellers->count(), 2),
        ];
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

        $commissionBase = $this->commissionService->eligibleLineSubtotalSql();
        $commissionAmount = $this->commissionService->lineCommissionAmountSql();

        $query = Sale::query()
            ->leftJoin('sale_items', function ($join) {
                $join
                    ->on('sale_items.sale_id', '=', 'sales.id')
                    ->whereNull('sale_items.deleted_at');
            });

        $this->commissionService->applyCommissionJoins($query)
            ->whereIn('sales.seller_id', $sellerIds)
            ->where('sales.status', Sale::STATUS_COMPLETED);

        $this->applySaleDateRange($query, $from, $to);

        return $query
            ->selectRaw('sales.seller_id')
            ->selectRaw('COUNT(DISTINCT sales.id) as completed_sales_count')
            ->selectRaw("COALESCE(SUM({$commissionBase}), 0) as commission_base")
            ->selectRaw("COALESCE(SUM({$commissionAmount}), 0) as commission_amount")
            ->groupBy('sales.seller_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->seller_id => [
                    'completed_sales_count' => (int) $row->completed_sales_count,
                    'commission_base' => (float) $row->commission_base,
                    'commission_amount' => (float) $row->commission_amount,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array{completed_sales_count: int, commission_base: float, commission_amount: float}>
     */
    private function sellerMetricsByMonth(int $sellerId, int $year): array
    {
        $commissionBase = $this->commissionService->eligibleLineSubtotalSql();
        $commissionAmount = $this->commissionService->lineCommissionAmountSql();
        $driver = DB::connection()->getDriverName();
        $periodExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', sales.created_at)"
            : "DATE_FORMAT(sales.created_at, '%Y-%m')";

        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();

        $query = Sale::query()
            ->leftJoin('sale_items', function ($join) {
                $join
                    ->on('sale_items.sale_id', '=', 'sales.id')
                    ->whereNull('sale_items.deleted_at');
            });

        $this->commissionService->applyCommissionJoins($query)
            ->where('sales.seller_id', $sellerId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.created_at', [$from, $to]);

        $rows = $query
            ->selectRaw("{$periodExpression} as period")
            ->selectRaw('COUNT(DISTINCT sales.id) as completed_sales_count')
            ->selectRaw("COALESCE(SUM({$commissionBase}), 0) as commission_base")
            ->selectRaw("COALESCE(SUM({$commissionAmount}), 0) as commission_amount")
            ->groupBy('period')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [
            (string) $row->period => [
                'completed_sales_count' => (int) $row->completed_sales_count,
                'commission_base' => (float) $row->commission_base,
                'commission_amount' => (float) $row->commission_amount,
            ],
        ])->all();
    }

    private function serializeSeller(Seller $seller, ?array $metrics = null): array
    {
        if ($metrics) {
            $completedSalesCount = (int) $metrics['completed_sales_count'];
            $commissionBase = (float) $metrics['commission_base'];
            $commissionAmount = (float) $metrics['commission_amount'];
        } else {
            [$from, $to] = $this->currentMonthRange();
            $monthMetrics = $this->sellerMetrics([$seller->id], $from, $to);
            $fallback = $monthMetrics[$seller->id] ?? [
                'completed_sales_count' => 0,
                'commission_base' => 0,
                'commission_amount' => 0,
            ];
            $completedSalesCount = (int) $fallback['completed_sales_count'];
            $commissionBase = (float) $fallback['commission_base'];
            $commissionAmount = (float) $fallback['commission_amount'];
        }

        return [
            ...$this->serializeSellerRates($seller),
            'completed_sales_count' => $completedSalesCount,
            'commission_base' => round($commissionBase, 2),
            'commission_amount' => round($commissionAmount, 2),
            'max_commission_rate' => round($this->commissionService->sellerMaxRate($seller), 2),
            'created_at' => $seller->created_at,
            'updated_at' => $seller->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSellerRates(Seller $seller): array
    {
        [
            $productsRate,
            $sparePartsRate,
            $maintenancePartsRate,
            $bikesForSaleRate,
            $maintenanceServicesRate,
        ] = $this->commissionService->sellerRates($seller);

        return [
            'id' => $seller->id,
            'name' => $seller->name,
            'phone' => $seller->phone,
            'products_commission_rate' => $productsRate,
            'spare_parts_commission_rate' => $sparePartsRate,
            'maintenance_parts_commission_rate' => $maintenancePartsRate,
            'bikes_for_sale_commission_rate' => $bikesForSaleRate,
            'maintenance_services_commission_rate' => $maintenanceServicesRate,
        ];
    }
}
