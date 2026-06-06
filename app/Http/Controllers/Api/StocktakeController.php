<?php

namespace App\Http\Controllers\Api;

use App\Exports\StocktakeDiscrepancyExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StocktakeExportRequest;
use App\Services\StocktakeService;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StocktakeController extends Controller
{
    public function __construct(private readonly StocktakeService $service) {}

    public function discrepancyExport(StocktakeExportRequest $request): BinaryFileResponse
    {
        $rows = $this->service->buildDiscrepancies($request->validated()['items']);

        $filename = 'inventory-count-discrepancies_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new StocktakeDiscrepancyExport($rows), $filename);
    }
}
