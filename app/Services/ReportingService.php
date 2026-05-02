<?php

namespace App\Services;

use App\Models\BikeForSale;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SparePart;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class ReportingService
{
    /**
     * @return array<string, mixed>
     */
    public function profitLoss(array $filters): array
    {
        $validated = $this->validateFilters($filters, false);
        $sales = $this->loadSales($validated);
        $expenseSummary = $this->expenseBuckets($validated);

        $currencies = $this->emptyCurrencyBuckets();

        foreach ($sales as $sale) {
            $allocations = $this->saleCurrencyAllocations($sale);

            foreach ($allocations as $currency => $values) {
                if ($this->currencyExcluded($validated, $currency)) {
                    continue;
                }

                if ($sale->status === Sale::STATUS_COMPLETED) {
                    $currencies[$currency]['revenue'] += $values['net_revenue'];
                    $currencies[$currency]['cogs'] += $values['cogs'];
                    $currencies[$currency]['gross_profit'] += $values['net_revenue'] - $values['cogs'];
                    $currencies[$currency]['revenue_by_type'][$sale->type] += $values['net_revenue'];
                    $currencies[$currency]['revenue_by_channel'][$sale->is_maintenance ? 'maintenance' : 'non_maintenance'] += $values['net_revenue'];
                }
            }
        }

        foreach (array_keys($currencies) as $currency) {
            $currencies[$currency]['operating_expenses'] = $expenseSummary[$currency]['total'];
            $currencies[$currency]['expense_categories'] = $expenseSummary[$currency]['categories'];
            $currencies[$currency]['net_profit'] = $currencies[$currency]['gross_profit'] - $currencies[$currency]['operating_expenses'];
        }

        return [
            'filters' => $this->normalizedFiltersPayload($validated),
            'currencies' => $this->filterCurrencyBuckets($currencies, $validated['currency'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceSheet(array $filters): array
    {
        $validated = $this->validateFilters($filters, false);
        $sales = $this->loadSales($validated);
        $inventory = $this->inventoryBuckets();
        $unpaidExpenses = $this->unpaidExpenseBuckets($validated);

        $currencies = $this->emptyCurrencyBuckets();

        foreach (array_keys($currencies) as $currency) {
            $currencies[$currency] = [
                'assets' => [
                    'cash_equivalents' => [
                        'total' => 0.0,
                        'payment_methods' => [],
                    ],
                    'inventory' => $inventory[$currency],
                    'accounts_receivable' => 0.0,
                    'total_assets' => 0.0,
                ],
                'liabilities' => [
                    'unpaid_expenses' => $unpaidExpenses[$currency]['total'],
                    'expense_categories' => $unpaidExpenses[$currency]['categories'],
                    'total_liabilities' => $unpaidExpenses[$currency]['total'],
                ],
                'equity' => 0.0,
            ];
        }

        foreach ($sales as $sale) {
            $allocations = $this->saleCurrencyAllocations($sale);

            foreach ($allocations as $currency => $values) {
                if ($this->currencyExcluded($validated, $currency)) {
                    continue;
                }

                if ($sale->status === Sale::STATUS_COMPLETED) {
                    $paymentMethod = $sale->paymentMethod?->name ?? 'Unknown';
                    $currencies[$currency]['assets']['cash_equivalents']['total'] += $values['net_revenue'];
                    $currencies[$currency]['assets']['cash_equivalents']['payment_methods'][$paymentMethod]
                        = ($currencies[$currency]['assets']['cash_equivalents']['payment_methods'][$paymentMethod] ?? 0) + $values['net_revenue'];
                }

                if (in_array($sale->status, [Sale::STATUS_PARTIAL, Sale::STATUS_PENDING], true)) {
                    $currencies[$currency]['assets']['accounts_receivable'] += $values['net_revenue'];
                }
            }
        }

        foreach (array_keys($currencies) as $currency) {
            $assets = &$currencies[$currency]['assets'];
            $liabilities = &$currencies[$currency]['liabilities'];

            $assets['cash_equivalents']['payment_methods'] = $this->roundMap($assets['cash_equivalents']['payment_methods']);
            $assets['cash_equivalents']['total'] = round($assets['cash_equivalents']['total'], 2);
            $assets['accounts_receivable'] = round($assets['accounts_receivable'], 2);
            $assets['total_assets'] = round(
                $assets['cash_equivalents']['total'] + $assets['inventory']['total'] + $assets['accounts_receivable'],
                2
            );

            $liabilities['expense_categories'] = $this->roundMap($liabilities['expense_categories']);
            $liabilities['unpaid_expenses'] = round($liabilities['unpaid_expenses'], 2);
            $liabilities['total_liabilities'] = round($liabilities['total_liabilities'], 2);

            $currencies[$currency]['equity'] = round(
                $assets['total_assets'] - $liabilities['total_liabilities'],
                2
            );
        }

        return [
            'filters' => $this->normalizedFiltersPayload($validated),
            'currencies' => $this->filterCurrencyBuckets($currencies, $validated['currency'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function annualSummary(array $filters): array
    {
        $validated = $this->validateFilters($filters, true);
        $sales = $this->loadSales($validated);
        $expenseSummary = $this->expenseBuckets($validated);
        $year = (int) $validated['year'];

        $currencies = [
            'EGP' => $this->emptyAnnualCurrencySummary(),
            'USD' => $this->emptyAnnualCurrencySummary(),
        ];

        foreach ($sales as $sale) {
            if ($sale->status !== Sale::STATUS_COMPLETED) {
                continue;
            }

            $monthKey = Carbon::parse($sale->created_at)->format('Y-m');
            $monthIndex = (int) Carbon::parse($sale->created_at)->format('n') - 1;
            $allocations = $this->saleCurrencyAllocations($sale);

            foreach ($allocations as $currency => $values) {
                if ($this->currencyExcluded($validated, $currency)) {
                    continue;
                }

                $currencies[$currency]['monthly'][$monthIndex]['revenue'] += $values['net_revenue'];
                $currencies[$currency]['monthly'][$monthIndex]['cogs'] += $values['cogs'];
                $currencies[$currency]['monthly'][$monthIndex]['gross_profit'] += $values['net_revenue'] - $values['cogs'];
                $currencies[$currency]['totals']['revenue'] += $values['net_revenue'];
                $currencies[$currency]['totals']['cogs'] += $values['cogs'];
                $currencies[$currency]['totals']['gross_profit'] += $values['net_revenue'] - $values['cogs'];
                $currencies[$currency]['revenue_mix'][$sale->type] += $values['net_revenue'];
                $currencies[$currency]['maintenance_revenue'] += $sale->is_maintenance ? $values['net_revenue'] : 0;
                $currencies[$currency]['non_maintenance_revenue'] += $sale->is_maintenance ? 0 : $values['net_revenue'];
            }
        }

        foreach (array_keys($currencies) as $currency) {
            for ($i = 0; $i < 12; $i++) {
                $month = Carbon::create($year, $i + 1, 1);
                $monthKey = $month->format('Y-m');
                $monthlyExpense = $expenseSummary[$currency]['monthly'][$monthKey] ?? 0.0;

                $currencies[$currency]['monthly'][$i]['month'] = $month->format('M');
                $currencies[$currency]['monthly'][$i]['expenses'] = round($monthlyExpense, 2);
                $currencies[$currency]['monthly'][$i]['net_profit'] = round(
                    $currencies[$currency]['monthly'][$i]['gross_profit'] - $monthlyExpense,
                    2
                );
                $currencies[$currency]['monthly'][$i]['revenue'] = round($currencies[$currency]['monthly'][$i]['revenue'], 2);
                $currencies[$currency]['monthly'][$i]['cogs'] = round($currencies[$currency]['monthly'][$i]['cogs'], 2);
                $currencies[$currency]['monthly'][$i]['gross_profit'] = round($currencies[$currency]['monthly'][$i]['gross_profit'], 2);
            }

            $currencies[$currency]['totals']['expenses'] = round($expenseSummary[$currency]['total'], 2);
            $currencies[$currency]['totals']['net_profit'] = round(
                $currencies[$currency]['totals']['gross_profit'] - $currencies[$currency]['totals']['expenses'],
                2
            );
            $currencies[$currency]['totals'] = $this->roundMap($currencies[$currency]['totals']);
            $currencies[$currency]['revenue_mix'] = $this->roundMap($currencies[$currency]['revenue_mix']);
            $currencies[$currency]['expense_categories'] = $this->roundMap($expenseSummary[$currency]['categories']);
            $currencies[$currency]['maintenance_revenue'] = round($currencies[$currency]['maintenance_revenue'], 2);
            $currencies[$currency]['non_maintenance_revenue'] = round($currencies[$currency]['non_maintenance_revenue'], 2);
            $currencies[$currency]['margin_percent'] = $currencies[$currency]['totals']['revenue'] > 0
                ? round(($currencies[$currency]['totals']['net_profit'] / $currencies[$currency]['totals']['revenue']) * 100, 2)
                : 0.0;
        }

        return [
            'filters' => $this->normalizedFiltersPayload($validated),
            'year' => $year,
            'currencies' => $this->filterCurrencyBuckets($currencies, $validated['currency'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function expensesSummary(array $filters): array
    {
        $validated = $this->validateFilters($filters, false);
        $query = Expense::query()->latest('incurred_on')->latest('id');

        if (! empty($validated['date_from'])) {
            $query->whereDate('incurred_on', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('incurred_on', '<=', $validated['date_to']);
        }

        if (! empty($validated['currency'])) {
            $query->where('currency', $validated['currency']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 20));
        $summary = $this->expenseBuckets($validated);

        return [
            'filters' => $this->normalizedFiltersPayload($validated),
            'summary' => [
                'EGP' => [
                    'total' => round($summary['EGP']['total'], 2),
                    'categories' => $this->roundMap($summary['EGP']['categories']),
                ],
                'USD' => [
                    'total' => round($summary['USD']['total'], 2),
                    'categories' => $this->roundMap($summary['USD']['categories']),
                ],
            ],
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(array $filters, bool $yearRequired): array
    {
        $validator = Validator::make($filters, [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'year' => [$yearRequired ? 'required' : 'nullable', 'integer', 'min:2000', 'max:2100'],
            'currency' => ['nullable', Rule::in(['EGP', 'USD'])],
            'payment_status' => ['nullable', Rule::in([Expense::STATUS_PAID, Expense::STATUS_UNPAID])],
            'category' => ['nullable', Rule::in(Expense::CATEGORIES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if ($yearRequired) {
            $startOfYear = Carbon::create((int) $validated['year'], 1, 1)->startOfDay();
            $endOfYear = Carbon::create((int) $validated['year'], 12, 31)->endOfDay();
            $validated['date_from'] = $validated['date_from'] ?? $startOfYear->toDateString();
            $validated['date_to'] = $validated['date_to'] ?? $endOfYear->toDateString();
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     * @return Collection<int, Sale>
     */
    private function loadSales(array $validated): Collection
    {
        $query = Sale::query()
            ->with([
                'paymentMethod',
                'items.product',
                'items.sparePart',
                'items.maintenanceService',
                'items.bikeForSale',
            ]);

        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        return $query->get();
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{EGP: array{total: float, categories: array<string, float>, monthly: array<string, float>}, USD: array{total: float, categories: array<string, float>, monthly: array<string, float>}}
     */
    private function expenseBuckets(array $validated): array
    {
        $query = Expense::query();

        if (! empty($validated['date_from'])) {
            $query->whereDate('incurred_on', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('incurred_on', '<=', $validated['date_to']);
        }

        if (! empty($validated['currency'])) {
            $query->where('currency', $validated['currency']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        $summary = [
            'EGP' => ['total' => 0.0, 'categories' => [], 'monthly' => []],
            'USD' => ['total' => 0.0, 'categories' => [], 'monthly' => []],
        ];

        foreach ($query->get() as $expense) {
            $currency = $expense->currency;
            $amount = (float) $expense->amount;
            $monthKey = Carbon::parse($expense->incurred_on)->format('Y-m');

            $summary[$currency]['total'] += $amount;
            $summary[$currency]['categories'][$expense->category] = ($summary[$currency]['categories'][$expense->category] ?? 0) + $amount;
            $summary[$currency]['monthly'][$monthKey] = ($summary[$currency]['monthly'][$monthKey] ?? 0) + $amount;
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{EGP: array{total: float, categories: array<string, float>}, USD: array{total: float, categories: array<string, float>}}
     */
    private function unpaidExpenseBuckets(array $validated): array
    {
        $validated['payment_status'] = Expense::STATUS_UNPAID;
        $summary = $this->expenseBuckets($validated);

        return [
            'EGP' => ['total' => $summary['EGP']['total'], 'categories' => $summary['EGP']['categories']],
            'USD' => ['total' => $summary['USD']['total'], 'categories' => $summary['USD']['categories']],
        ];
    }

    /**
     * @return array{EGP: array<string, mixed>, USD: array<string, mixed>}
     */
    private function inventoryBuckets(): array
    {
        $inventory = [
            'EGP' => ['products' => 0.0, 'spare_parts' => 0.0, 'bikes' => 0.0, 'total' => 0.0],
            'USD' => ['products' => 0.0, 'spare_parts' => 0.0, 'bikes' => 0.0, 'total' => 0.0],
        ];

        foreach (Product::query()->get() as $product) {
            $value = (float) $product->cost_price * (int) $product->stock_quantity;
            $inventory[$product->currency_pricing]['products'] += $value;
        }

        foreach (SparePart::query()->get() as $sparePart) {
            $value = (float) $sparePart->cost_price * (int) $sparePart->stock_quantity;
            $inventory[$sparePart->currency_pricing]['spare_parts'] += $value;
        }

        foreach (BikeForSale::query()->where('status', '!=', 'sold')->get() as $bike) {
            $value = (float) $bike->cost_price;
            $inventory[$bike->currency_pricing]['bikes'] += $value;
        }

        foreach (array_keys($inventory) as $currency) {
            $inventory[$currency]['products'] = round($inventory[$currency]['products'], 2);
            $inventory[$currency]['spare_parts'] = round($inventory[$currency]['spare_parts'], 2);
            $inventory[$currency]['bikes'] = round($inventory[$currency]['bikes'], 2);
            $inventory[$currency]['total'] = round(
                $inventory[$currency]['products'] + $inventory[$currency]['spare_parts'] + $inventory[$currency]['bikes'],
                2
            );
        }

        return $inventory;
    }

    /**
     * @return array{EGP: array<string, mixed>, USD: array<string, mixed>}
     */
    private function emptyCurrencyBuckets(): array
    {
        return [
            'EGP' => [
                'revenue' => 0.0,
                'cogs' => 0.0,
                'gross_profit' => 0.0,
                'operating_expenses' => 0.0,
                'net_profit' => 0.0,
                'revenue_by_type' => [
                    Sale::TYPE_SITE => 0.0,
                    Sale::TYPE_ONLINE => 0.0,
                    Sale::TYPE_DELIVERY => 0.0,
                ],
                'revenue_by_channel' => [
                    'maintenance' => 0.0,
                    'non_maintenance' => 0.0,
                ],
                'expense_categories' => [],
            ],
            'USD' => [
                'revenue' => 0.0,
                'cogs' => 0.0,
                'gross_profit' => 0.0,
                'operating_expenses' => 0.0,
                'net_profit' => 0.0,
                'revenue_by_type' => [
                    Sale::TYPE_SITE => 0.0,
                    Sale::TYPE_ONLINE => 0.0,
                    Sale::TYPE_DELIVERY => 0.0,
                ],
                'revenue_by_channel' => [
                    'maintenance' => 0.0,
                    'non_maintenance' => 0.0,
                ],
                'expense_categories' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAnnualCurrencySummary(): array
    {
        $monthly = [];
        for ($i = 0; $i < 12; $i++) {
            $monthly[] = [
                'month' => '',
                'revenue' => 0.0,
                'cogs' => 0.0,
                'gross_profit' => 0.0,
                'expenses' => 0.0,
                'net_profit' => 0.0,
            ];
        }

        return [
            'monthly' => $monthly,
            'totals' => [
                'revenue' => 0.0,
                'cogs' => 0.0,
                'gross_profit' => 0.0,
                'expenses' => 0.0,
                'net_profit' => 0.0,
            ],
            'revenue_mix' => [
                Sale::TYPE_SITE => 0.0,
                Sale::TYPE_ONLINE => 0.0,
                Sale::TYPE_DELIVERY => 0.0,
            ],
            'expense_categories' => [],
            'maintenance_revenue' => 0.0,
            'non_maintenance_revenue' => 0.0,
            'margin_percent' => 0.0,
        ];
    }

    /**
     * @return array<string, array{gross_revenue: float, net_revenue: float, cogs: float}>
     */
    private function saleCurrencyAllocations(Sale $sale): array
    {
        $buckets = [
            'EGP' => ['gross_revenue' => 0.0, 'net_revenue' => 0.0, 'cogs' => 0.0],
            'USD' => ['gross_revenue' => 0.0, 'net_revenue' => 0.0, 'cogs' => 0.0],
        ];

        /** @var SaleItem $item */
        foreach ($sale->items as $item) {
            $currency = $this->resolveItemCurrency($item);
            if ($currency === null) {
                continue;
            }

            $remainingQty = $item->remainingQty();
            $grossRevenue = ((float) $item->selling_price - (float) $item->discount) * $remainingQty;
            $cogs = $this->resolveItemCost($item) * $remainingQty;

            $buckets[$currency]['gross_revenue'] += $grossRevenue;
            $buckets[$currency]['cogs'] += $cogs;
        }

        $grossTotal = $buckets['EGP']['gross_revenue'] + $buckets['USD']['gross_revenue'];

        foreach (array_keys($buckets) as $currency) {
            if ($grossTotal <= 0) {
                $buckets[$currency]['net_revenue'] = 0.0;
                continue;
            }

            $share = $buckets[$currency]['gross_revenue'] / $grossTotal;
            $allocatedShipping = (float) $sale->shipping_fee * $share;
            $allocatedDiscount = (float) $sale->discount * $share;
            $buckets[$currency]['net_revenue'] = round(
                $buckets[$currency]['gross_revenue'] + $allocatedShipping - $allocatedDiscount,
                2
            );
            $buckets[$currency]['cogs'] = round($buckets[$currency]['cogs'], 2);
            $buckets[$currency]['gross_revenue'] = round($buckets[$currency]['gross_revenue'], 2);
        }

        return $buckets;
    }

    private function resolveItemCurrency(SaleItem $item): ?string
    {
        return match (true) {
            $item->product !== null => $item->product->currency_pricing,
            $item->sparePart !== null => $item->sparePart->currency_pricing,
            $item->maintenanceService !== null => $item->maintenanceService->currency_pricing,
            $item->bikeForSale !== null => $item->bikeForSale->currency_pricing,
            default => null,
        };
    }

    private function resolveItemCost(SaleItem $item): float
    {
        return match (true) {
            $item->product !== null => (float) $item->product->cost_price,
            $item->sparePart !== null => (float) $item->sparePart->cost_price,
            $item->maintenanceService !== null => 0.0,
            $item->bikeForSale !== null => (float) $item->bikeForSale->cost_price,
            default => 0.0,
        };
    }

    private function currencyExcluded(array $validated, string $currency): bool
    {
        return ! empty($validated['currency']) && $validated['currency'] !== $currency;
    }

    /**
     * @param array<string, mixed> $buckets
     * @return array<string, mixed>
     */
    private function filterCurrencyBuckets(array $buckets, ?string $currency): array
    {
        if ($currency === null) {
            return $buckets;
        }

        return [$currency => $buckets[$currency]];
    }

    /**
     * @param array<string, float> $values
     * @return array<string, float>
     */
    private function roundMap(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function normalizedFiltersPayload(array $validated): array
    {
        return [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'year' => $validated['year'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'payment_status' => $validated['payment_status'] ?? null,
            'category' => $validated['category'] ?? null,
        ];
    }
}
