<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalRequestApproveRequest;
use App\Http\Requests\ApprovalRequestIndexRequest;
use App\Http\Requests\ApprovalRequestRejectRequest;
use App\Http\Requests\ApprovalRequestStoreRequest;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\ApprovalRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalRequestController extends Controller
{
    public function __construct(private readonly ApprovalRequestService $service)
    {
    }

    public function index(ApprovalRequestIndexRequest $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->user(), $request->validated());

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (ApprovalRequest $item) => $this->service->serialize($item))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function pendingCount(Request $request): JsonResponse
    {
        if ($request->user()?->role !== User::ROLE_ADMIN) {
            abort(403, 'Only administrators can view pending request counts.');
        }

        return response()->json([
            'count' => $this->service->pendingCount(),
        ]);
    }

    public function show(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $record = $this->service->findForActor($request->user(), $approvalRequest->id);

        return response()->json($this->service->serialize($record));
    }

    public function store(ApprovalRequestStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $record = $this->service->createSaleDiscountRequest($request->user(), $validated);

        return response()->json($this->service->serialize($record), 201);
    }

    public function approve(
        ApprovalRequestApproveRequest $request,
        ApprovalRequest $approvalRequest,
    ): JsonResponse {
        $record = $this->service->approve(
            $request->user(),
            $approvalRequest,
            $request->validated(),
        );

        return response()->json($this->service->serialize($record));
    }

    public function reject(
        ApprovalRequestRejectRequest $request,
        ApprovalRequest $approvalRequest,
    ): JsonResponse {
        $record = $this->service->reject(
            $request->user(),
            $approvalRequest,
            $request->input('rejection_reason'),
        );

        return response()->json($this->service->serialize($record));
    }

    public function destroy(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $record = $this->service->cancel($request->user(), $approvalRequest);

        return response()->json($this->service->serialize($record));
    }
}
