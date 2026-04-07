<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BikeInventory extends Model
{
    use HasFactory;

    protected $table = 'bikes_inventory';

    public const TYPE_OWNED = 'owned';

    public const TYPE_CONSIGNMENT = 'consignment';

    protected $fillable = [
        'bike_id',
        'type',
        'brand',
        'model',
        'year',
        'cost_price',
        'selling_price',
        'mileage',
        'cc',
        'horse_power',
        'owner_name',
        'owner_phone',
        'notes',
    ];

    protected $appends = [
        'is_sold',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'mileage' => 'integer',
            'cc' => 'integer',
            'horse_power' => 'integer',
        ];
    }

    public function bike(): BelongsTo
    {
        return $this->belongsTo(Bike::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'item_id')
            ->where('item_type', SaleItem::ITEM_TYPE_BIKE);
    }

    public function getIsSoldAttribute(): bool
    {
        if ($this->relationLoaded('saleItems')) {
            return $this->saleItems->isNotEmpty();
        }

        return $this->saleItems()->exists();
    }
}
