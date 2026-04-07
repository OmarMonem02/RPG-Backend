<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPaymentRequest;
use App\Http\Requests\AddSaleItemRequest;
use App\Http\Requests\CalculateSaleRequest;
use App\Http\Requests\ListSalesRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SaleResource;
use App\Models\Invoice;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use App\Services\Sales\AddItemToSaleService;
use App\Services\Sales\AddPaymentService;
use App\Services\Sales\CalculateSaleService;
use App\Services\Sales\CompleteSaleService;
use App\Services\Sales\CreateSaleService;
use App\Services\Sales\ListSalesService;
use App\Services\Sales\ReturnSaleService;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function __construct(
        private readonly CreateSaleService $createSaleService,
        private readonly AddItemToSaleService $addItemToSaleService,
        private readonly AddPaymentService $addPaymentService,
        private readonly CompleteSaleService $completeSaleService,
        private readonly ReturnSaleService $returnSaleService,
        private readonly ListSalesService $listSalesService,
        private readonly CalculateSaleService $calculateSaleService,
    ) {}

    public function index(ListSalesRequest $request): JsonResponse
    {
        $sales = $this->listSalesService->execute($request->validated());

        return $this->successResponse('Sales retrieved successfully.', [
            'items' => SaleResource::collection($sales->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    public function show(Sale $sale): JsonResponse
    {
        return $this->successResponse(
            'Sale retrieved successfully.',
            new SaleResource($sale->load(['customer', 'seller', 'items', 'payments', 'invoice']))
        );
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->createSaleService->execute($request->validated());

        return $this->successResponse('Sale created successfully.', new SaleResource($sale), 201);
    }

    public function addItem(AddSaleItemRequest $request, Sale $sale): JsonResponse
    {
        $sale = $this->addItemToSaleService->execute($sale, $request->validated());

        return $this->successResponse('Item added to sale successfully.', new SaleResource($sale));
    }

    public function addPayment(AddPaymentRequest $request, Sale $sale): JsonResponse
    {
        $sale = $this->addPaymentService->execute($sale, $request->validated());

        return $this->successResponse('Payment added successfully.', new SaleResource($sale));
    }

    public function complete(Sale $sale): JsonResponse
    {
        $sale = $this->completeSaleService->execute($sale);

        return $this->successResponse('Sale completed successfully.', new SaleResource($sale));
    }

    public function returnSale(Sale $sale): JsonResponse
    {
        $sale = $this->returnSaleService->execute($sale);

        return $this->successResponse('Sale returned successfully.', new SaleResource($sale));
    }

    public function calculate(CalculateSaleRequest $request): JsonResponse
    {
        return $this->successResponse(
            'Sale totals calculated successfully.',
            $this->calculateSaleService->execute($request->validated())
        );
    }

    public function invoice(Sale $sale): JsonResponse
    {
        $sale->load(['customer', 'seller', 'items', 'payments']);
        $invoice = $sale->invoice ?? Invoice::query()->where([
            'type' => Invoice::TYPE_SALE,
            'reference_id' => $sale->id,
        ])->first();

        return $this->successResponse('Sale invoice retrieved successfully.', [
            'sale' => (new SaleResource($sale))->resolve(),
            'invoice' => $invoice ? (new InvoiceResource($invoice))->resolve() : null,
        ]);
    }
}
