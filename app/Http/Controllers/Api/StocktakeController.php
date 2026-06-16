<?php

namespace App\Http\Controllers\Api;

use App\Exports\StocktakeDiscrepancyExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StocktakeExportRequest;
use App\Services\StocktakeService;
use App\Support\Export\ExportColumnResolver;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StocktakeController extends Controller
{
    public function __construct(
        private readonly StocktakeService $service,
        private readonly ExportColumnResolver $columnResolver,
    ) {}

    public function discrepancyExport(StocktakeExportRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();
        $rows = $this->service->buildDiscrepancies($validated['items']);
        $columnKeys = $this->columnResolver->resolve('stocktake', $validated['columns'] ?? null);

        $filename = 'inventory-count-discrepancies_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new StocktakeDiscrepancyExport($rows, $columnKeys), $filename);
    }
}
