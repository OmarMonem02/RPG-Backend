<?php

namespace App\Models;

use App\Traits\LogsHistory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItem extends Model
{
    use SoftDeletes, LogsHistory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'spare_part_id',
        'maintenance_service_id',
        'bike_for_sale_id',
        'selling_price',
        'discount',
        'qty',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function maintenanceService(): BelongsTo
    {
        return $this->belongsTo(MaintenanceService::class);
    }

    public function bikeForSale(): BelongsTo
    {
        return $this->belongsTo(BikeForSale::class, 'bike_for_sale_id');
    }
}
