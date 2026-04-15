<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeForSale extends Model
{
    use SoftDeletes, LogsHistory;

    protected $table = 'bike_for_sale';

    protected $fillable = [
        'bike_blueprint_id',
        'currency_pricing',
        'cost_price',
        'sale_price',
        'status',
        'max_discount_type',
        'max_discount_value',
        'vin',
        'mileage',
        'notes',
    ];

    public function bikeBlueprint(): BelongsTo
    {
        return $this->belongsTo(BikeBlueprint::class);
    }

    // Scopes
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where('vin', 'like', "%{$search}%")
            ->orWhereHas('bikeBlueprint', function ($q) use ($search) {
                $q->where('model', 'like', "%{$search}%");
            });
    }

    public function scopeByStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeByPrice($query, ?float $minPrice = null, ?float $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query = $query->where('sale_price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query = $query->where('sale_price', '<=', $maxPrice);
        }

        return $query;
    }

    public function scopeByBlueprint($query, ?int $blueprintId)
    {
        return $blueprintId ? $query->where('bike_blueprint_id', $blueprintId) : $query;
    }

    public function scopeByCurrency($query, ?string $currency)
    {
        return $currency ? $query->where('currency_pricing', $currency) : $query;
    }
}
