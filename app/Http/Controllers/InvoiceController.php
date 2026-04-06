<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Invoices\ListInvoicesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly ListInvoicesService $listInvoicesService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $invoices = $this->listInvoicesService->execute($request->only([
            'type',
            'status',
            'from_date',
            'to_date',
        ]));

        return response()->json([
            'message' => 'Invoices retrieved successfully.',
            'data' => $invoices,
        ]);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'message' => 'Invoice retrieved successfully.',
            'data' => $this->listInvoicesService->show($invoice),
        ]);
    }
}
