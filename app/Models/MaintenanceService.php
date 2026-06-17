<?php

namespace App\Models;

use App\Support\CaseInsensitiveLike;
use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceService extends Model
{
    use SoftDeletes, LogsHistory;

    protected $fillable = [
        'name',
        'sale_currency',
        'service_price',
        'max_discount_type',
        'max_discount_value',
        'maintenance_service_sector_id',
        'have_commission',
    ];

    protected $casts = [
        'have_commission' => 'boolean',
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

        return $query->where(function ($q) use ($search) {
            CaseInsensitiveLike::where($q, 'name', $search);
            $q->orWhereHas('sector', function ($sector) use ($search) {
                CaseInsensitiveLike::where($sector, 'name', $search);
            });
        });
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
        return $currency ? $query->where('sale_currency', $currency) : $query;
    }
}
