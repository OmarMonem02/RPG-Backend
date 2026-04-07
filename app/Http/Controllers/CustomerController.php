<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\Customers\CreateCustomerService;
use App\Services\Customers\DeleteCustomerService;
use App\Services\Customers\UpdateCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CreateCustomerService $createCustomerService,
        private readonly UpdateCustomerService $updateCustomerService,
        private readonly DeleteCustomerService $deleteCustomerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $customers = Customer::query()
            ->withCount(['customerBikes', 'sales', 'tickets'])
            ->when($request->filled('phone'), fn ($query) => $query->where('phone', 'like', '%'.$request->string('phone').'%'))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Customers retrieved successfully.',
            'data' => $customers,
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json([
            'message' => 'Customer retrieved successfully.',
            'data' => $customer->load(['customerBikes'])->loadCount(['sales', 'tickets']),
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->createCustomerService->execute($request->validated());

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->updateCustomerService->execute($customer, $request->validated());

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => $customer,
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->deleteCustomerService->execute($customer);

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}
