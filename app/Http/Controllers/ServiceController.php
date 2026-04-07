<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Services\Services\CreateServiceService;
use App\Services\Services\DeleteServiceService;
use App\Services\Services\UpdateServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private readonly CreateServiceService $createServiceService,
        private readonly UpdateServiceService $updateServiceService,
        private readonly DeleteServiceService $deleteServiceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $services = Service::query()
            ->with('category')
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Services retrieved successfully.',
            'data' => $services,
        ]);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'message' => 'Service retrieved successfully.',
            'data' => $service->load('category'),
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->createServiceService->execute($request->validated());

        return response()->json([
            'message' => 'Service created successfully.',
            'data' => $service,
        ], 201);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service = $this->updateServiceService->execute($service, $request->validated());

        return response()->json([
            'message' => 'Service updated successfully.',
            'data' => $service,
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->deleteServiceService->execute($service);

        return response()->json([
            'message' => 'Service deleted successfully.',
        ]);
    }
}
