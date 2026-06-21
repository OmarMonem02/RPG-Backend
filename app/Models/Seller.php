<?php

namespace App\Models;

use App\Support\CaseInsensitiveLike;
use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use LogsHistory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'commission_rate',
        'products_commission_rate',
        'spare_parts_commission_rate',
        'maintenance_parts_commission_rate',
        'bikes_for_sale_commission_rate',
        'maintenance_services_commission_rate',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'products_commission_rate' => 'decimal:2',
        'spare_parts_commission_rate' => 'decimal:2',
        'maintenance_parts_commission_rate' => 'decimal:2',
        'bikes_for_sale_commission_rate' => 'decimal:2',
        'maintenance_services_commission_rate' => 'decimal:2',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeSearch($query, ?string $search)
    {
        $term = trim((string) $search);

        if ($term === '') {
            return $query;
        }

        return $query->where(function ($query) use ($term) {
            CaseInsensitiveLike::where($query, 'name', $term);
            CaseInsensitiveLike::orWhere($query, 'phone', $term);

            if (ctype_digit($term)) {
                $query->orWhere('id', (int) $term);
            }
        });
    }
}
