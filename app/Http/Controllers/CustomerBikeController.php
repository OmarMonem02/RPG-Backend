<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerBikeRequest;
use App\Http\Requests\UpdateCustomerBikeRequest;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Services\Customers\CreateCustomerBikeService;
use App\Services\Customers\DeleteCustomerBikeService;
use App\Services\Customers\UpdateCustomerBikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerBikeController extends Controller
{
    public function __construct(
        private readonly CreateCustomerBikeService $createCustomerBikeService,
        private readonly UpdateCustomerBikeService $updateCustomerBikeService,
        private readonly DeleteCustomerBikeService $deleteCustomerBikeService,
    ) {}

    public function index(Request $request, ?Customer $customer = null): JsonResponse
    {
        $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $customerId = $customer?->id ?? $request->integer('customer_id');

        $customerBikes = CustomerBike::query()
            ->with('customer')
            ->withCount('tickets')
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');

                $query->where(function ($query) use ($search): void {
                    $query->where('brand', 'like', '%'.$search.'%')
                        ->orWhere('model', 'like', '%'.$search.'%')
                        ->orWhere('year', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Customer bikes retrieved successfully.',
            'data' => $customerBikes,
        ]);
    }

    public function show(CustomerBike $customerBike): JsonResponse
    {
        return response()->json([
            'message' => 'Customer bike retrieved successfully.',
            'data' => $customerBike->load('customer')->loadCount('tickets'),
        ]);
    }

    public function store(StoreCustomerBikeRequest $request, ?Customer $customer = null): JsonResponse
    {
        $customer ??= Customer::query()->findOrFail($request->validated('customer_id'));

        $customerBike = $this->createCustomerBikeService->execute($customer, $request->validated());

        return response()->json([
            'message' => 'Customer bike created successfully.',
            'data' => $customerBike->load('customer'),
        ], 201);
    }

    public function update(UpdateCustomerBikeRequest $request, CustomerBike $customerBike): JsonResponse
    {
        $customerBike = $this->updateCustomerBikeService->execute($customerBike, $request->validated());

        return response()->json([
            'message' => 'Customer bike updated successfully.',
            'data' => $customerBike,
        ]);
    }

    public function destroy(CustomerBike $customerBike): JsonResponse
    {
        $this->deleteCustomerBikeService->execute($customerBike);

        return response()->json([
            'message' => 'Customer bike deleted successfully.',
        ]);
    }
}
