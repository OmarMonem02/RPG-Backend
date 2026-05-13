<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerIndexRequest;
use App\Http\Requests\CustomerWorkspaceRequest;
use App\Models\Customer;
use App\Services\CustomerWorkspaceService;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerWorkspaceService $customerWorkspaceService,
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
}
