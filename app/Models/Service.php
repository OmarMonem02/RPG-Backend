<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    public const DISCOUNT_TYPE_FIXED = 'fixed';

    protected $fillable = [
        'category_id',
        'name',
        'price',
        'max_discount_type',
        'max_discount_value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_discount_value' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function ticketItems(): HasMany
    {
        return $this->hasMany(TicketItem::class, 'item_id')
            ->where('item_type', TicketItem::ITEM_TYPE_SERVICE);
    }

    public function calculateMaxDiscount(?float $amount = null): float
    {
        $baseAmount = $amount ?? (float) $this->price;

        if ($this->max_discount_type === self::DISCOUNT_TYPE_PERCENTAGE) {
            return round($baseAmount * ((float) $this->max_discount_value / 100), 2);
        }

        return round((float) $this->max_discount_value, 2);
    }

    public function discountWithinLimit(float $discount, ?float $amount = null): bool
    {
        return round($discount, 2) <= $this->calculateMaxDiscount($amount);
    }
}
