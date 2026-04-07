<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListSalePaymentsRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\Payments\DeletePaymentService;
use App\Services\Payments\ListSalePaymentsService;
use App\Services\Payments\UpdatePaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ListSalePaymentsService $listSalePaymentsService,
        private readonly UpdatePaymentService $updatePaymentService,
        private readonly DeletePaymentService $deletePaymentService,
    ) {}

    public function index(ListSalePaymentsRequest $request, Sale $sale): JsonResponse
    {
        $payments = $this->listSalePaymentsService->execute($sale, $request->validated()['per_page'] ?? 15);

        return $this->successResponse('Payments retrieved successfully.', [
            'items' => PaymentResource::collection($payments->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->updatePaymentService->execute($payment, $request->validated());

        return $this->successResponse('Payment updated successfully.', new PaymentResource($payment));
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->deletePaymentService->execute($payment);

        return $this->successResponse('Payment deleted successfully.');
    }
}
