<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Console\Command;

class RecalculateSaleTotalsCommand extends Command
{
    protected $signature = 'sales:recalculate-totals {--sale-id= : Recalculate a single sale by ID}';

    protected $description = 'Recalculate sale totals from current line items (fixes stale totals after returns/exchanges).';

    public function handle(SaleService $saleService): int
    {
        $saleId = $this->option('sale-id');

        $query = Sale::query()->orderBy('id');

        if ($saleId !== null && $saleId !== '') {
            $query->whereKey((int) $saleId);
        }

        $sales = $query->get();

        if ($sales->isEmpty()) {
            $this->warn('No sales matched the given criteria.');

            return self::FAILURE;
        }

        $corrected = 0;

        foreach ($sales as $sale) {
            $before = (float) $sale->total;
            $saleService->refreshSaleTotals($sale);
            $sale->refresh();

            if (abs($before - (float) $sale->total) > 0.009) {
                $corrected++;
                $this->line("Sale #{$sale->id}: {$before} → {$sale->total}");
            }
        }

        $this->info("Processed {$sales->count()} sale(s). Corrected {$corrected} total(s).");

        return self::SUCCESS;
    }
}
