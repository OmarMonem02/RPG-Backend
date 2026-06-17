<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerIndexRequest;
use App\Http\Requests\CustomerWorkspaceRequest;
use App\Http\Requests\StoreCustomerAddressRequest;
use App\Http\Requests\StoreCustomerBikeRequest;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Services\CustomerAddressService;
use App\Services\CustomerWorkspaceService;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerWorkspaceService $customerWorkspaceService,
        private readonly CustomerAddressService $customerAddressService,
    ) {
    }

    public function index(CustomerIndexRequest $request): JsonResponse
    {
        return response()->json(
            $this->customerWorkspaceService->paginateCustomers($request->validated()),
        );
    }

    public function workspace(CustomerWorkspaceRequest $request, Customer $customer): JsonResponse
    {
        return response()->json(
            $this->customerWorkspaceService->buildWorkspace($customer, $request->validated()),
        );
    }

    public function storeBike(StoreCustomerBikeRequest $request, Customer $customer): JsonResponse
    {
        $bike = CustomerBike::query()->create([
            'customer_id' => $customer->id,
            ...$request->validated(),
        ]);

        $bike->load(['bikeBlueprint.brand']);

        return response()->json($bike, 201);
    }

    public function indexAddresses(Customer $customer): JsonResponse
    {
        $addresses = $this->customerAddressService
            ->listForCustomer($customer)
            ->map(fn ($address) => $this->customerAddressService->serializeAddress($address))
            ->values()
            ->all();

        return response()->json(['data' => $addresses]);
    }

    public function storeAddress(StoreCustomerAddressRequest $request, Customer $customer): JsonResponse
    {
        $address = $this->customerAddressService->create($customer, $request->validated());

        return response()->json(
            $this->customerAddressService->serializeAddress($address),
            201,
        );
    }
}
