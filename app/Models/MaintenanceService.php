<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceService extends Model
{
    use SoftDeletes, LogsHistory;

    protected $fillable = [
        'name',
        'currency_pricing',
        'service_price',
        'max_discount_type',
        'max_discount_value',
        'maintenance_service_sector_id',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(MaintenanceServiceSector::class, 'maintenance_service_sector_id');
    }

    // Scopes
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where('name', 'like', "%{$search}%");
    }

    public function scopeBySector($query, ?int $sectorId)
    {
        return $sectorId ? $query->where('maintenance_service_sector_id', $sectorId) : $query;
    }

    public function scopeByPrice($query, ?float $minPrice = null, ?float $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query = $query->where('service_price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query = $query->where('service_price', '<=', $maxPrice);
        }

        return $query;
    }

    public function scopeByCurrency($query, ?string $currency)
    {
        return $currency ? $query->where('currency_pricing', $currency) : $query;
    }
}
