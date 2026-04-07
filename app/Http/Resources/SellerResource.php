<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalRevenue = round(
            (float) ($this->total_revenue ?? ((float) ($this->total_sales_gross ?? 0) - (float) ($this->total_sales_discount ?? 0))),
            2
        );
        $totalSalesCount = (int) ($this->total_sales_count ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'commission_type' => $this->commission_type,
            'commission_value' => (float) $this->commission_value,
            'status' => $this->status,
            'total_sales_count' => $totalSalesCount,
            'total_revenue' => $totalRevenue,
            'average_sale_value' => $totalSalesCount > 0 ? round($totalRevenue / $totalSalesCount, 2) : 0,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
