<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;

class CreateSaleService
{
    public function __construct(
        private readonly AssertSaleSellerIsActiveService $assertSaleSellerIsActiveService,
    ) {}

    public function execute(array $data): Sale
    {
        return DB::transaction(function () use ($data): Sale {
            $customerId = $data['customer_id'] ?? null;

            if ($customerId === null && isset($data['customer'])) {
                $customerId = Customer::query()->create($data['customer'])->id;
            }

            $seller = null;

            if (isset($data['seller_id']) && $data['seller_id'] !== null) {
                $seller = Seller::query()->withTrashed()->find($data['seller_id']);
                $this->assertSaleSellerIsActiveService->execute($seller);
            }

            $sale = Sale::query()->create([
                'customer_id' => $customerId,
                'seller_id' => $seller?->id,
                'total' => 0,
                'discount' => 0,
                'status' => Sale::STATUS_PENDING,
                'type' => $data['type'],
            ]);

            return $sale->load(['customer', 'seller', 'items', 'payments']);
        });
    }
}
