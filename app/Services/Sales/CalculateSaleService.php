<?php

namespace App\Services\Sales;

use App\Models\BikeInventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SaleItem;
use Illuminate\Validation\ValidationException;

class CalculateSaleService
{
    public function execute(array $data): array
    {
        $items = collect($data['items'])->map(function (array $item): array {
            return match ($item['item_type']) {
                SaleItem::ITEM_TYPE_PRODUCT => $this->productLine($item),
                SaleItem::ITEM_TYPE_BIKE => $this->bikeLine($item),
                default => throw ValidationException::withMessages([
                    'items' => 'Unsupported item type provided.',
                ]),
            };
        });

        $payments = collect($data['payments'] ?? [])
            ->map(function (array $payment): array {
                return [
                    'amount' => round((float) $payment['amount'], 2),
                    'method' => $payment['method'],
                    'status' => $payment['status'] ?? Payment::STATUS_COMPLETED,
                ];
            });

        $total = round((float) $items->sum(fn (array $item) => $item['unit_price'] * $item['qty']), 2);
        $discount = round((float) $items->sum('discount'), 2);
        $finalAmount = max(round($total - $discount, 2), 0);
        $paidAmount = round((float) $payments
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount'), 2);

        return [
            'items' => $items->values()->all(),
            'payments' => $payments->values()->all(),
            'summary' => [
                'total' => $total,
                'discount' => $discount,
                'final_amount' => $finalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => max(round($finalAmount - $paidAmount, 2), 0),
                'payment_status' => $this->resolvePaymentStatus($finalAmount, $paidAmount),
            ],
        ];
    }

    private function productLine(array $item): array
    {
        $product = Product::query()->findOrFail($item['item_id']);
        $qty = (int) $item['qty'];
        $discount = round((float) ($item['discount'] ?? 0), 2);

        if ($product->qty < $qty) {
            throw ValidationException::withMessages([
                'items' => "Requested quantity for product {$product->id} exceeds available stock.",
            ]);
        }

        $maxDiscount = $product->calculateMaxDiscount((float) $product->selling_price * $qty);

        if ($discount > $maxDiscount) {
            throw ValidationException::withMessages([
                'items' => "Discount for product {$product->id} exceeds the maximum allowed amount.",
            ]);
        }

        return [
            'item_type' => SaleItem::ITEM_TYPE_PRODUCT,
            'item_id' => $product->id,
            'item_name' => $product->name,
            'qty' => $qty,
            'unit_price' => (float) $product->selling_price,
            'discount' => $discount,
            'line_total' => max(round(((float) $product->selling_price * $qty) - $discount, 2), 0),
        ];
    }

    private function bikeLine(array $item): array
    {
        $bike = BikeInventory::query()->findOrFail($item['item_id']);
        $discount = round((float) ($item['discount'] ?? 0), 2);

        if ($discount > (float) $bike->selling_price) {
            throw ValidationException::withMessages([
                'items' => "Discount for bike inventory {$bike->id} exceeds the selling price.",
            ]);
        }

        return [
            'item_type' => SaleItem::ITEM_TYPE_BIKE,
            'item_id' => $bike->id,
            'item_name' => trim("{$bike->brand} {$bike->model}"),
            'qty' => 1,
            'unit_price' => (float) $bike->selling_price,
            'discount' => $discount,
            'line_total' => max(round((float) $bike->selling_price - $discount, 2), 0),
        ];
    }

    private function resolvePaymentStatus(float $finalAmount, float $paidAmount): string
    {
        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($paidAmount < $finalAmount) {
            return 'partial';
        }

        return 'paid';
    }
}
