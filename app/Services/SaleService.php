<?php

namespace App\Services;

use App\Models\BikeForSale;
use App\Models\CustomerSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SparePart;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function create(array $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += ((float) $item['selling_price'] - (float) ($item['discount'] ?? 0)) * (int) $item['qty'];
            }

            $sale = Sale::create([
                'customer_id' => $data['customer_id'],
                'user_id' => $userId,
                'seller_id' => $data['seller_id'] ?? null,
                'total' => $subtotal + (float) ($data['shipping_fee'] ?? 0) - (float) ($data['discount'] ?? 0),
                'discount' => (float) ($data['discount'] ?? 0),
                'payment_method_id' => $data['payment_method_id'],
                'type' => $data['type'],
                'status' => $data['status'],
                'delivery_status' => $data['delivery_status'] ?? null,
                'shipping_fee' => (float) ($data['shipping_fee'] ?? 0),
                'is_maintenance' => (bool) ($data['is_maintenance'] ?? false),
            ]);

            foreach ($data['items'] as $itemData) {
                $sale->items()->create([
                    'product_id' => $itemData['product_id'] ?? null,
                    'spare_part_id' => $itemData['spare_part_id'] ?? null,
                    'maintenance_service_id' => $itemData['maintenance_service_id'] ?? null,
                    'bike_for_sale_id' => $itemData['bike_for_sale_id'] ?? null,
                    'selling_price' => $itemData['selling_price'],
                    'discount' => $itemData['discount'] ?? 0,
                    'qty' => $itemData['qty'],
                ]);

                if (! empty($itemData['product_id'])) {
                    $product = Product::find($itemData['product_id']);
                    if ($product) {
                        $product->decrement('stock_quantity', (int) $itemData['qty']);
                    }
                }
                if (! empty($itemData['spare_part_id'])) {
                    $sparePart = SparePart::find($itemData['spare_part_id']);
                    if ($sparePart) {
                        $sparePart->decrement('stock_quantity', (int) $itemData['qty']);
                    }
                }
                if (! empty($itemData['bike_for_sale_id'])) {
                    $bike = BikeForSale::find($itemData['bike_for_sale_id']);
                    if ($bike) {
                        $bike->update(['status' => 'sold']);
                    }
                }
            }

            CustomerSale::updateOrCreate(
                ['customer_id' => $data['customer_id'], 'sale_id' => $sale->id],
                ['customer_id' => $data['customer_id'], 'sale_id' => $sale->id],
            );

            return $sale->load(['items', 'customer', 'user', 'seller', 'paymentMethod']);
        });
    }
}
