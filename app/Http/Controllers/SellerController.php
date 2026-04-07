<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListSellerSalesRequest;
use App\Http\Requests\ListSellersRequest;
use App\Http\Requests\StoreSellerRequest;
use App\Http\Requests\UpdateSellerRequest;
use App\Http\Requests\UpdateSellerStatusRequest;
use App\Http\Resources\SaleResource;
use App\Http\Resources\SellerResource;
use App\Models\Seller;
use App\Services\Sellers\CreateSellerService;
use App\Services\Sellers\DeleteSellerService;
use App\Services\Sellers\ListSellerSalesService;
use App\Services\Sellers\ListSellersService;
use App\Services\Sellers\ShowSellerService;
use App\Services\Sellers\UpdateSellerService;
use App\Services\Sellers\UpdateSellerStatusService;
use Illuminate\Http\JsonResponse;

class SellerController extends Controller
{
    public function __construct(
        private readonly ListSellersService $listSellersService,
        private readonly CreateSellerService $createSellerService,
        private readonly UpdateSellerService $updateSellerService,
        private readonly DeleteSellerService $deleteSellerService,
        private readonly UpdateSellerStatusService $updateSellerStatusService,
        private readonly ShowSellerService $showSellerService,
        private readonly ListSellerSalesService $listSellerSalesService,
    ) {}

    public function index(ListSellersRequest $request): JsonResponse
    {
        $sellers = $this->listSellersService->execute($request->validated());

        return $this->successResponse('Sellers retrieved successfully.', [
            'items' => SellerResource::collection($sellers->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $sellers->currentPage(),
                'last_page' => $sellers->lastPage(),
                'per_page' => $sellers->perPage(),
                'total' => $sellers->total(),
            ],
        ]);
    }

    public function store(StoreSellerRequest $request): JsonResponse
    {
        $seller = $this->createSellerService->execute($request->validated());

        return $this->successResponse('Seller created successfully.', new SellerResource($seller), 201);
    }

    public function show(Seller $seller): JsonResponse
    {
        $payload = $this->showSellerService->execute($seller);

        return $this->successResponse('Seller retrieved successfully.', [
            'seller' => (new SellerResource($payload['seller']))->resolve(),
            'last_sales' => SaleResource::collection($payload['last_sales'])->resolve(),
        ]);
    }

    public function update(UpdateSellerRequest $request, Seller $seller): JsonResponse
    {
        $seller = $this->updateSellerService->execute($seller, $request->validated());

        return $this->successResponse('Seller updated successfully.', new SellerResource($seller));
    }

    public function destroy(Seller $seller): JsonResponse
    {
        $this->deleteSellerService->execute($seller);

        return $this->successResponse('Seller deleted successfully.');
    }

    public function updateStatus(UpdateSellerStatusRequest $request, Seller $seller): JsonResponse
    {
        $seller = $this->updateSellerStatusService->execute($seller, $request->validated()['status']);

        return $this->successResponse('Seller status updated successfully.', new SellerResource($seller));
    }

    public function sales(ListSellerSalesRequest $request, Seller $seller): JsonResponse
    {
        $sales = $this->listSellerSalesService->execute($seller, $request->validated());

        return $this->successResponse('Seller sales retrieved successfully.', [
            'items' => SaleResource::collection($sales->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }
}
