<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleCatalogRequest;
use App\Http\Requests\SaleExchangeRequest;
use App\Http\Requests\SaleFilterRequest;
use App\Http\Requests\SaleHistoryRequest;
use App\Http\Requests\SaleItemStoreRequest;
use App\Http\Requests\SaleItemUpdateRequest;
use App\Http\Requests\SaleRequest;
use App\Http\Requests\SaleReturnRequest;
use App\Http\Requests\SaleUpdateRequest;
use App\Http\Resources\HistoryResource;
use App\Exports\SalesListExport;
use App\Exports\UnstoredSaleItemsExport;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SaleInventoryService;
use App\Services\SaleService;
use App\Support\Export\ExportColumnResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly ExportColumnResolver $columnResolver,
    ) {
    }

    public function index(SaleFilterRequest $request): JsonResponse
    {
        return response()->json($this->saleService->paginateSales($request->validated()));
    }

    public function export(SaleFilterRequest $request, SaleInventoryService $inventory): BinaryFileResponse|JsonResponse
    {
        $filters = $request->validated();
        unset($filters['page'], $filters['per_page']);

        $useUnstoredExport = ! empty($filters['has_unstored_items']);
        $query = $useUnstoredExport
            ? $this->saleService->exportUnstoredSaleItemsQuery($filters)
            : $this->saleService->exportSalesQuery($filters);
        $count = (clone $query)->count();

        if ($count > 50_000) {
            return response()->json([
                'message' => 'Too many rows match these filters. Narrow your filters and try again. Maximum allowed is 50,000 rows.',
                'matched_count' => $count,
            ], 422);
        }

        $format = match (strtolower((string) $request->query('format', 'xlsx'))) {
            'csv' => ExcelFormat::CSV,
            default => ExcelFormat::XLSX,
        };

        $suffix = $format === ExcelFormat::CSV ? '.csv' : '.xlsx';
        $filename = ($useUnstoredExport ? 'unstored_sale_items_' : 'sales_export_')
            . now()->format('Ymd_His') . $suffix;

        $columnKeys = $this->columnResolver->resolve(
            $useUnstoredExport ? 'unstored_sale_items' : 'sales',
            $request->query('columns'),
        );

        return Excel::download(
            $useUnstoredExport
                ? new UnstoredSaleItemsExport($query, $columnKeys)
                : new SalesListExport($query, $inventory, $columnKeys),
            $filename,
            $format,
        );
    }

    public function store(SaleRequest $request): JsonResponse
    {
        $sale = $this->saleService->create($request->validated(), (int) $request->user()->id);

        return response()->json($sale, 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json($this->saleService->getSaleDetails($sale));
    }

    public function adjustments(Request $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->paginateAdjustments($sale, (int) $request->query('per_page', 20))
        );
    }

    public function history(SaleHistoryRequest $request, Sale $sale): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $this->saleService->paginateSaleHistory(
            $sale,
            (int) ($validated['per_page'] ?? 50),
        );

        return response()->json(HistoryResource::collection($paginator)->response()->getData(true));
    }

    public function catalog(SaleCatalogRequest $request): JsonResponse
    {
        return response()->json($this->saleService->catalog($request->validated()));
    }

    public function update(SaleUpdateRequest $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->updateSale($sale, $request->validated(), (int) $request->user()->id)
        );
    }

    public function addItem(SaleItemStoreRequest $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->addItem($sale, $request->validated(), (int) $request->user()->id)
        );
    }

    public function updateItem(SaleItemUpdateRequest $request, Sale $sale, SaleItem $saleItem): JsonResponse
    {
        return response()->json(
            $this->saleService->updateItem($sale, $saleItem, $request->validated(), (int) $request->user()->id)
        );
    }

    public function removeItem(Request $request, Sale $sale, SaleItem $saleItem): JsonResponse
    {
        return response()->json(
            $this->saleService->removeItem($sale, $saleItem, (int) $request->user()->id)
        );
    }

    public function returns(SaleReturnRequest $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->processReturn($sale, $request->validated(), (int) $request->user()->id)
        );
    }

    public function exchanges(SaleExchangeRequest $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->processExchange($sale, $request->validated(), (int) $request->user()->id)
        );
    }

    public function destroy(Request $request, Sale $sale): JsonResponse
    {
        $this->saleService->delete($sale, (int) $request->user()->id);

        return response()->json([], 204);
    }
}
