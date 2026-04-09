<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceService extends Model
{
    use SoftDeletes;

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
}
