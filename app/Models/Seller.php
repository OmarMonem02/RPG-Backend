<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const COMMISSION_TYPE_TOTAL = 'total';

    public const COMMISSION_TYPE_PROFIT = 'profit';

    protected $fillable = [
        'name',
        'commission_type',
        'commission_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
        ];
    }

    public function scopeWithSellerMetrics(Builder $query): Builder
    {
        return $query
            ->withCount('sales as total_sales_count')
            ->withSum('sales as total_sales_gross', 'total')
            ->withSum('sales as total_sales_discount', 'discount');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
