<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItem extends Model
{
    use SoftDeletes, LogsHistory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PARTIALLY_RETURNED = 'partially_returned';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_EXCHANGED = 'exchanged';

    protected $fillable = [
        'sale_id',
        'product_id',
        'spare_part_id',
        'maintenance_service_id',
        'bike_for_sale_id',
        'selling_price',
        'discount',
        'qty',
        'returned_qty',
        'status',
        'replaced_from_sale_item_id',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'qty' => 'integer',
        'returned_qty' => 'integer',
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

    public function replacedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_from_sale_item_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replaced_from_sale_item_id');
    }

    public function remainingQty(): int
    {
        return max(0, (int) $this->qty - (int) $this->returned_qty);
    }

    public function getSellableType(): ?string
    {
        return match (true) {
            ! is_null($this->product_id) => 'product',
            ! is_null($this->spare_part_id) => 'spare_part',
            ! is_null($this->maintenance_service_id) => 'maintenance_service',
            ! is_null($this->bike_for_sale_id) => 'bike',
            default => null,
        };
    }
}
