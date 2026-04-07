<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'item_type' => $this->item_type,
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'qty' => (int) $this->qty,
            'price_snapshot' => (float) $this->price_snapshot,
            'selling_price_at_time' => (float) ($this->selling_price_at_time ?? $this->price_snapshot),
            'cost_price_at_time' => $this->cost_price_at_time !== null ? (float) $this->cost_price_at_time : null,
            'discount' => (float) $this->discount,
            'line_total' => (float) $this->line_total,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
