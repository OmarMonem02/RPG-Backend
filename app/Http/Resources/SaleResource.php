<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'seller_id' => $this->seller_id,
            'status' => $this->status,
            'type' => $this->type,
            'item_count' => (int) ($this->resource->items_count ?? $this->whenLoaded('items', fn () => $this->items->count(), 0)),
            'total' => (float) $this->total,
            'discount' => (float) $this->discount,
            'final_amount' => (float) $this->final_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'seller_commission' => (float) $this->seller_commission,
            'payment_status' => $this->payment_status,
            'customer_summary' => $this->whenLoaded('customer', fn (): ?array => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null),
            'seller_summary' => $this->whenLoaded('seller', fn (): ?array => $this->seller ? [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'status' => $this->seller->status,
            ] : null),
            'customer' => $this->whenLoaded('customer'),
            'seller' => $this->whenLoaded('seller'),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'invoice' => $this->whenLoaded('invoice', fn () => (new InvoiceResource($this->invoice))->resolve()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
